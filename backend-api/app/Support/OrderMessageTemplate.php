<?php

namespace App\Support;

/**
 * Render outlet-defined WhatsApp templates for order status notifications.
 *
 * Placeholders are simple {nama_pelanggan} style tokens — missing values fall
 * back to empty strings so a template never explodes on a partially-populated
 * order (e.g. a takeaway with no member name).
 */
class OrderMessageTemplate
{
    /**
     * Sensible defaults used when an outlet has not customized the template.
     */
    public const DEFAULTS = [
        // Shared / legacy fallbacks (used when no type-specific template exists)
        'approved' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan Anda dengan kode *{kode_pesanan}* di *{nama_outlet}* telah *DISETUJUI* dan sedang diproses.\n" .
            "Mohon ditunggu, terima kasih telah memesan!",
        'rejected' =>
            "Halo {nama_pelanggan},\n" .
            "Mohon maaf, pesanan Anda dengan kode *{kode_pesanan}* di *{nama_outlet}* tidak dapat kami proses (*DITOLAK*).\n" .
            "{alasan}\n" .
            "Silakan hubungi kasir untuk informasi lebih lanjut. Terima kasih.",
        'processing' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan Anda *{kode_pesanan}* di *{nama_outlet}* sedang *DIPROSES* oleh dapur/bar kami.\n" .
            "Pantau status pesanan Anda di sini:\n{link_tracking}\n" .
            "Mohon ditunggu sebentar lagi.",
        // Dine-in (meja) / takeaway variants for approved / rejected / processing.
        'approved_dinein' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan meja Anda dengan kode *{kode_pesanan}* di *{nama_outlet}* telah *DISETUJUI* dan akan segera disiapkan untuk meja {nomor_meja}.\n" .
            "Pantau status pesanan Anda di sini:\n{link_tracking}\n" .
            "Terima kasih sudah memesan!",
        'approved_takeaway' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan *takeaway* Anda dengan kode *{kode_pesanan}* di *{nama_outlet}* telah *DISETUJUI* dan sedang disiapkan untuk diambil.\n" .
            "Pantau status pesanan Anda di sini:\n{link_tracking}\n" .
            "Terima kasih sudah memesan!",
        'rejected_dinein' =>
            "Halo {nama_pelanggan},\n" .
            "Mohon maaf, pesanan meja Anda *{kode_pesanan}* di *{nama_outlet}* *DITOLAK*.\n" .
            "{alasan}\n" .
            "Silakan menghubungi kasir di lokasi untuk informasi lebih lanjut. Terima kasih.",
        'rejected_takeaway' =>
            "Halo {nama_pelanggan},\n" .
            "Mohon maaf, pesanan takeaway Anda *{kode_pesanan}* di *{nama_outlet}* *DITOLAK*.\n" .
            "{alasan}\n" .
            "Jika sudah melakukan pembayaran, silakan hubungi kasir untuk proses refund. Terima kasih.",
        'processing_dinein' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan meja Anda *{kode_pesanan}* di *{nama_outlet}* sedang *DIPROSES* untuk meja {nomor_meja}.\n" .
            "Pantau status pesanan Anda di sini:\n{link_tracking}\n" .
            "Mohon ditunggu sebentar lagi.",
        'processing_takeaway' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan *takeaway* Anda *{kode_pesanan}* di *{nama_outlet}* sedang *DIPROSES*.\n" .
            "Pantau status pesanan Anda di sini:\n{link_tracking}\n" .
            "Kami akan kabari lagi saat pesanan siap diambil.",
        'ready_dinein' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan Anda *{kode_pesanan}* di *{nama_outlet}* sudah *SIAP* dan akan segera diantar ke meja {nomor_meja}.\n" .
            "Selamat menikmati!",
        'ready_takeaway' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan takeaway Anda *{kode_pesanan}* di *{nama_outlet}* sudah *SIAP DIAMBIL* di kasir.\n" .
            "Terima kasih sudah memesan!",
        'completed_dinein' =>
            "Halo {nama_pelanggan},\n" .
            "Seluruh pesanan Anda *{kode_pesanan}* di *{nama_outlet}* sudah *SELESAI* dan telah diantar ke meja {nomor_meja}.\n" .
            "Selamat menikmati & terima kasih sudah memesan!",
        'completed_takeaway' =>
            "Halo {nama_pelanggan},\n" .
            "Seluruh pesanan takeaway Anda *{kode_pesanan}* di *{nama_outlet}* sudah *SELESAI* dan telah diserahkan ke pelanggan.\n" .
            "Terima kasih sudah memesan!",
    ];

    /**
     * Render a template string with placeholders.
     * Missing placeholders become empty strings and consecutive blank lines
     * are collapsed so optional fields (e.g. {alasan}) do not leave gaps.
     */
    public static function render(?string $template, array $vars, string $fallbackKey): string
    {
        $tpl = trim((string) $template) !== ''
            ? $template
            : (self::DEFAULTS[$fallbackKey] ?? '');

        $replacements = [];
        foreach ($vars as $k => $v) {
            $replacements['{' . $k . '}'] = (string) ($v ?? '');
        }

        $out = strtr($tpl, $replacements);
        // Strip leftover placeholders the caller did not supply
        $out = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '', $out);
        // Collapse 3+ newlines (created by empty optional lines) to 2
        $out = preg_replace("/\n{3,}/", "\n\n", $out);
        return trim($out);
    }

    /**
     * Build the variable map used by all templates from an order row.
     * Expects $order to be the orders table row (object with same fields).
     */
    public static function vars(
        object $order,
        string $outletName,
        ?string $reason = null,
        ?string $trackingUrl = null
    ): array {
        $type = (string) ($order->order_type ?? '');
        $tipeLabel = match ($type) {
            'dine_in'  => 'Dine-in',
            'takeaway' => 'Takeaway',
            'delivery' => 'Delivery',
            default    => $type,
        };

        return [
            'nama_pelanggan' => (string) ($order->customer_name ?? ''),
            'kode_pesanan'   => (string) ($order->kode ?? ''),
            'nama_outlet'    => (string) $outletName,
            'tipe_pesanan'   => $tipeLabel,
            'nomor_meja'     => (string) ($order->table_number ?? ''),
            'total'          => isset($order->total_amount)
                ? 'Rp ' . number_format((float) $order->total_amount, 0, ',', '.')
                : '',
            'status'         => (string) ($order->status ?? ''),
            'alasan'         => $reason ? 'Alasan: ' . $reason : '',
            'link_tracking'  => (string) ($trackingUrl ?? ''),
        ];
    }

    /**
     * Build the public tracking URL for an order. The hash matches
     * frontend-app/src/utils/outletId.js (XOR with the same seed) so the
     * link can be opened anonymously by the customer.
     */
    public static function buildTrackingUrl(int $outletId, string $orderCode): string
    {
        $base = rtrim((string) env('FRONTEND_URL', config('app.url', '')), '/');
        if ($base === '') {
            return '';
        }
        $encoded = self::encodeOutletId($outletId);
        return $base . '/track/' . $encoded . '/' . rawurlencode($orderCode);
    }

    /** Must match SEED in frontend-app/src/utils/outletId.js */
    private const OUTLET_ID_SEED = 0x504F5300;

    private static function encodeOutletId(int $id): string
    {
        $n = ($id ^ self::OUTLET_ID_SEED) & 0xFFFFFFFF;
        return str_pad(dechex($n), 8, '0', STR_PAD_LEFT);
    }
}
