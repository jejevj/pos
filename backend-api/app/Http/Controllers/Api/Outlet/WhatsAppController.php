<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Services\WahaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppController extends Controller
{
    protected WahaService $waha;

    public function __construct(WahaService $waha)
    {
        $this->waha = $waha;
    }

    protected function authorizeOutlet($outletId)
    {
        $outlet = \App\Models\Outlet::find($outletId);
        if (!$outlet) abort(404, 'Outlet not found');
        return $outlet;
    }

    /**
     * Get WAHA status and outlet WA settings.
     */
    public function status(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        DB::statement("SET search_path TO {$outlet->schema_name}, public");
        $settings = $this->getOutletWaSettings($outlet->schema_name);
        DB::statement("SET search_path TO public");

        return response()->json([
            'waha_enabled'     => config('waha.enabled'),
            'session_running'  => $this->waha->isSessionRunning(),
            'settings'         => $settings,
        ]);
    }

    /**
     * Get QR code for WAHA session.
     */
    public function qrCode()
    {
        $qr = $this->waha->getQrCode();
        return response()->json(['qr' => $qr]);
    }

    /**
     * Start WAHA session.
     */
    public function startSession()
    {
        $started = $this->waha->startSession();
        return response()->json(['success' => $started]);
    }

    /**
     * Get outlet WhatsApp notification settings.
     */
    public function getSettings(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        DB::statement("SET search_path TO {$outlet->schema_name}, public");
        $settings = $this->getOutletWaSettings($outlet->schema_name);
        DB::statement("SET search_path TO public");

        return response()->json($settings);
    }

    /**
     * Update outlet WhatsApp notification settings.
     */
    public function updateSettings(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $request->validate([
            'owner_phone'              => 'nullable|string|max:20',
            'notify_payroll'           => 'boolean',
            'notify_kasbon'            => 'boolean',
            'notify_low_stock'         => 'boolean',
            'notify_order'             => 'boolean',
        ]);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $this->ensureWaSettingsTable();

            $existing = DB::table('wa_settings')->first();
            $data = [
                'owner_phone'      => $request->owner_phone,
                'notify_payroll'   => $request->boolean('notify_payroll', true),
                'notify_kasbon'    => $request->boolean('notify_kasbon', true),
                'notify_low_stock' => $request->boolean('notify_low_stock', true),
                'notify_order'     => $request->boolean('notify_order', false),
                'updated_at'       => now(),
            ];

            if ($existing) {
                DB::table('wa_settings')->where('id', $existing->id)->update($data);
            } else {
                DB::table('wa_settings')->insert(array_merge($data, ['created_at' => now()]));
            }

            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Settings saved']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Send a test message.
     */
    public function sendTest(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $request->validate([
            'phone' => 'required|string',
        ]);

        $sent = $this->waha->sendText(
            $request->phone,
            "✅ *Test Notifikasi WAHA*\n\nHalo dari *{$outlet->name}*!\nKoneksi WhatsApp berhasil."
        );

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'Pesan terkirim' : 'Gagal mengirim (cek log)',
        ]);
    }

    // -------------------------------------------------------------------------

    protected function getOutletWaSettings(string $schema): array
    {
        $this->ensureWaSettingsTable();
        $row = DB::table('wa_settings')->first();

        return [
            'owner_phone'      => $row->owner_phone ?? null,
            'notify_payroll'   => (bool) ($row->notify_payroll ?? true),
            'notify_kasbon'    => (bool) ($row->notify_kasbon ?? true),
            'notify_low_stock' => (bool) ($row->notify_low_stock ?? true),
            'notify_order'     => (bool) ($row->notify_order ?? false),
        ];
    }

    protected function ensureWaSettingsTable(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('wa_settings')) {
            DB::statement("
                CREATE TABLE wa_settings (
                    id SERIAL PRIMARY KEY,
                    owner_phone VARCHAR(20),
                    notify_payroll BOOLEAN DEFAULT TRUE,
                    notify_kasbon BOOLEAN DEFAULT TRUE,
                    notify_low_stock BOOLEAN DEFAULT TRUE,
                    notify_order BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
            ");
        }
    }
}
