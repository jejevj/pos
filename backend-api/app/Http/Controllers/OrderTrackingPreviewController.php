<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Renders an HTML page with Open Graph / Twitter meta tags for the public
 * order tracking link so that when the link is shared on WhatsApp, Telegram,
 * Facebook, etc., the message preview shows the outlet name, order code and
 * outlet logo as a card.
 *
 * Real browsers are immediately redirected (JS + meta refresh) to the Vue
 * SPA so the customer keeps the existing tracking experience. Crawlers /
 * link previewers stop at the meta tags because they don't execute JS.
 *
 * Routes:
 *   GET /track/{outletId}/{orderCode}        — meta-enabled preview shell
 *   GET /track/{outletId}/{orderCode}/cover  — share cover image (PNG/SVG)
 */
class OrderTrackingPreviewController extends Controller
{
    /** Must match SEED in frontend-app/src/utils/outletId.js */
    private const OUTLET_ID_SEED = 0x504F5300;

    public function show(Request $request, string $outletId, string $orderCode)
    {
        $info = $this->lookup($outletId, $orderCode);

        $outletName = $info['outletName'];
        $orderKode  = $info['orderKode'] ?? $orderCode;
        $statusText = $info['statusText'];
        $siteName   = $info['siteName'] ?? config('app.name', 'POS');

        $baseUrl = $this->baseUrl($request);
        // The canonical share URL — same path the customer clicks from WA.
        $canonicalUrl = $baseUrl . '/track/' . rawurlencode($outletId) . '/' . rawurlencode($orderCode);
        $coverUrl     = $baseUrl . '/track/' . rawurlencode($outletId) . '/' . rawurlencode($orderCode) . '/cover';
        // Force browsers (not crawlers) over to the SPA. We append ?spa=1 so
        // nginx can route the same /track/* path to the frontend without
        // looping back to this controller.
        $spaUrl = $canonicalUrl . '?spa=1';

        $title = $outletName
            ? "Pesanan #{$orderKode} - {$outletName}"
            : "Pesanan #{$orderKode}";

        $description = $statusText
            ? "Status: {$statusText}. Klik untuk pantau pesanan Anda secara real-time."
            : "Pantau status pesanan Anda secara real-time.";

        return response()
            ->view('tracking.preview', [
                'title'        => $title,
                'description'  => $description,
                'canonicalUrl' => $canonicalUrl,
                'spaUrl'       => $spaUrl,
                'coverUrl'     => $coverUrl,
                'siteName'     => $siteName,
            ])
            ->header('Cache-Control', 'public, max-age=60')
            ->header('X-Robots-Tag', 'noindex'); // tracking pages should not be indexed
    }

    public function cover(Request $request, string $outletId, string $orderCode)
    {
        $info = $this->lookup($outletId, $orderCode);
        $logoPath = $info['logoPath'] ?? null;

        // If we have a usable raster/SVG file on the public disk, stream it
        // directly with the right content-type. Logos may be stored either
        // as a /storage/... URL (site_logo upload) or as a raw base64
        // data-URI (outlet.logo from the legacy outlet editor).
        $binary = $this->resolveImage($logoPath);
        if ($binary !== null) {
            return response($binary['data'], 200, [
                'Content-Type'  => $binary['mime'],
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        // No usable logo — fall back to a deterministic SVG cover that shows
        // the outlet initials and order code. SVG is supported by WhatsApp's
        // crawler when served with the correct content type.
        $svg = $this->fallbackSvg($info['outletName'] ?? 'POS', $info['orderKode'] ?? $orderCode);
        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Decode the outlet id and look up the outlet + order (read-only).
     * Returns null fields when not found so the preview still renders a
     * sane fallback card.
     */
    private function lookup(string $outletId, string $orderCode): array
    {
        $numericId = $this->resolveOutletId($outletId);
        $outlet = $numericId ? Outlet::find($numericId) : null;

        $outletName = $outlet?->name;
        $logoPath   = $outlet?->logo;
        $orderKode  = $orderCode;
        $statusText = null;

        if ($outlet) {
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                $order = DB::table('orders')
                    ->select('kode', 'status', 'kitchen_status')
                    ->where('kode', $orderCode)
                    ->whereNull('deleted_at')
                    ->first();
                if ($order) {
                    $orderKode  = $order->kode;
                    $statusText = $this->statusLabel($order->kitchen_status ?: $order->status);
                }
            } catch (\Throwable $e) {
                // Tracking preview is best-effort; never explode here.
            } finally {
                try { DB::statement("SET search_path TO public"); } catch (\Throwable $e) {}
            }
        }

        // Site fallback for branding (name + logo) when the outlet has none.
        $siteName = null;
        $siteLogo = null;
        try {
            $rows = DB::table('site_settings')
                ->whereIn('key', ['site_name', 'site_logo'])
                ->pluck('value', 'key');
            $siteName = $rows['site_name'] ?? null;
            $siteLogo = $rows['site_logo'] ?? null;
        } catch (\Throwable $e) {
            // site_settings table may not exist yet.
        }

        return [
            'outletName' => $outletName ?: $siteName,
            'orderKode'  => $orderKode,
            'statusText' => $statusText,
            'logoPath'   => $logoPath ?: $siteLogo,
            'siteName'   => $siteName,
        ];
    }

    private function statusLabel(?string $s): ?string
    {
        return match (strtolower((string) $s)) {
            'pending'     => 'Pesanan Diterima',
            'preparing', 'processing' => 'Sedang Diproses',
            'ready'       => 'Siap Disajikan',
            'served', 'completed', 'paid' => 'Sudah Disajikan',
            'cancelled', 'canceled', 'rejected' => 'Dibatalkan',
            default       => null,
        };
    }

    private function resolveImage(?string $logo): ?array
    {
        if (!$logo) return null;

        // base64 data URI ("data:image/png;base64,...")
        if (preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#i', $logo, $m)) {
            $data = base64_decode($m[2], true);
            return $data === false ? null : ['mime' => $m[1], 'data' => $data];
        }

        // /storage/... URL — map to local public disk file
        $path = ltrim(parse_url($logo, PHP_URL_PATH) ?: $logo, '/');
        if (str_starts_with($path, 'storage/')) {
            $rel = substr($path, strlen('storage/'));
            if (Storage::disk('public')->exists($rel)) {
                $mime = Storage::disk('public')->mimeType($rel) ?: 'application/octet-stream';
                return ['mime' => $mime, 'data' => Storage::disk('public')->get($rel)];
            }
        }

        return null;
    }

    private function fallbackSvg(string $outletName, string $orderKode): string
    {
        $initials = strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9 ]/', '', $outletName) ?: 'POS', 0, 2));
        $name = htmlspecialchars($outletName, ENT_QUOTES, 'UTF-8');
        $kode = htmlspecialchars($orderKode, ENT_QUOTES, 'UTF-8');
        $ini  = htmlspecialchars($initials, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#5D87FF"/>
      <stop offset="1" stop-color="#13DEB9"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#g)"/>
  <circle cx="600" cy="240" r="120" fill="#ffffff" fill-opacity="0.15"/>
  <text x="600" y="280" font-family="Arial, Helvetica, sans-serif" font-size="120" font-weight="700"
        text-anchor="middle" fill="#ffffff">{$ini}</text>
  <text x="600" y="430" font-family="Arial, Helvetica, sans-serif" font-size="56" font-weight="700"
        text-anchor="middle" fill="#ffffff">Pesanan #{$kode}</text>
  <text x="600" y="500" font-family="Arial, Helvetica, sans-serif" font-size="36"
        text-anchor="middle" fill="#ffffff" fill-opacity="0.9">{$name}</text>
</svg>
SVG;
    }

    private function resolveOutletId(string $raw): ?int
    {
        if ($raw === '') return null;
        if (preg_match('/^[0-9a-fA-F]{8}$/', $raw)) {
            $n = (hexdec($raw) ^ self::OUTLET_ID_SEED) & 0xFFFFFFFF;
            return $n > 0 ? (int) $n : null;
        }
        if (is_numeric($raw)) {
            $n = (int) $raw;
            return $n > 0 ? $n : null;
        }
        return null;
    }

    /**
     * Absolute base URL for og:url / og:image. Prefer FRONTEND_URL so the
     * share link points at whatever hostname the customer actually opens;
     * fall back to APP_URL, then to the inbound request host.
     */
    private function baseUrl(Request $request): string
    {
        $frontend = (string) env('FRONTEND_URL', '');
        if ($frontend !== '') {
            return rtrim($frontend, '/');
        }
        $app = (string) config('app.url', '');
        if ($app !== '') {
            return rtrim($app, '/');
        }
        return rtrim($request->getSchemeAndHttpHost(), '/');
    }
}
