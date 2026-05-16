<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Services\WahaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppController extends Controller
{
    use AuthorizesOutletAccess;

    protected WahaService $waha;

    public function __construct(WahaService $waha)
    {
        $this->waha = $waha;
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
     * Get QR code for WAHA session. Restricted to platform admins since the
     * WAHA session is a process-wide resource (anyone who can scan/start it
     * can route WA messages for every outlet).
     */
    public function qrCode()
    {
        $this->requireSuperAdmin();
        $qr = $this->waha->getQrCode();
        return response()->json(['qr' => $qr]);
    }

    /**
     * Start WAHA session. Platform-admin only — see qrCode().
     */
    public function startSession()
    {
        $this->requireSuperAdmin();
        $started = $this->waha->startSession();
        return response()->json(['success' => $started]);
    }

    private function requireSuperAdmin(): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user || !method_exists($user, 'isSuperAdmin') || !$user->isSuperAdmin()) {
            abort(response()->json([
                'message' => 'Hanya superadmin yang boleh mengelola sesi WAHA global.',
                'code' => 'SUPERADMIN_REQUIRED',
            ], 403));
        }
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
            'notify_processing'        => 'boolean',
            'notify_ready'             => 'boolean',
            'notify_completed'         => 'boolean',
            'tpl_approved'             => 'nullable|string|max:2000',
            'tpl_rejected'             => 'nullable|string|max:2000',
            'tpl_processing'           => 'nullable|string|max:2000',
            'tpl_ready_dinein'         => 'nullable|string|max:2000',
            'tpl_ready_takeaway'       => 'nullable|string|max:2000',
            'tpl_completed_dinein'     => 'nullable|string|max:2000',
            'tpl_completed_takeaway'   => 'nullable|string|max:2000',
        ]);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $this->ensureWaSettingsTable();

            $existing = DB::table('wa_settings')->first();
            $data = [
                'owner_phone'        => $request->owner_phone,
                'notify_payroll'     => $request->boolean('notify_payroll', true),
                'notify_kasbon'      => $request->boolean('notify_kasbon', true),
                'notify_low_stock'   => $request->boolean('notify_low_stock', true),
                'notify_order'       => $request->boolean('notify_order', false),
                'notify_processing'     => $request->boolean('notify_processing', true),
                'notify_ready'          => $request->boolean('notify_ready', true),
                'notify_completed'      => $request->boolean('notify_completed', true),
                'tpl_approved'          => $request->input('tpl_approved'),
                'tpl_rejected'          => $request->input('tpl_rejected'),
                'tpl_processing'        => $request->input('tpl_processing'),
                'tpl_ready_dinein'      => $request->input('tpl_ready_dinein'),
                'tpl_ready_takeaway'    => $request->input('tpl_ready_takeaway'),
                'tpl_completed_dinein'  => $request->input('tpl_completed_dinein'),
                'tpl_completed_takeaway'=> $request->input('tpl_completed_takeaway'),
                'updated_at'            => now(),
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
            'owner_phone'         => $row->owner_phone ?? null,
            'notify_payroll'      => (bool) ($row->notify_payroll ?? true),
            'notify_kasbon'       => (bool) ($row->notify_kasbon ?? true),
            'notify_low_stock'    => (bool) ($row->notify_low_stock ?? true),
            'notify_order'        => (bool) ($row->notify_order ?? false),
            'notify_processing'      => (bool) ($row->notify_processing ?? true),
            'notify_ready'           => (bool) ($row->notify_ready ?? true),
            'notify_completed'       => (bool) ($row->notify_completed ?? true),
            'tpl_approved'           => $row->tpl_approved ?? null,
            'tpl_rejected'           => $row->tpl_rejected ?? null,
            'tpl_processing'         => $row->tpl_processing ?? null,
            'tpl_ready_dinein'       => $row->tpl_ready_dinein ?? null,
            'tpl_ready_takeaway'     => $row->tpl_ready_takeaway ?? null,
            'tpl_completed_dinein'   => $row->tpl_completed_dinein ?? null,
            'tpl_completed_takeaway' => $row->tpl_completed_takeaway ?? null,
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
                    notify_processing BOOLEAN DEFAULT TRUE,
                    notify_ready BOOLEAN DEFAULT TRUE,
                    tpl_approved TEXT,
                    tpl_rejected TEXT,
                    tpl_processing TEXT,
                    tpl_ready_dinein TEXT,
                    tpl_ready_takeaway TEXT,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
            ");
        }
        // Heal columns for outlets provisioned before template support
        DB::statement("ALTER TABLE wa_settings ADD COLUMN IF NOT EXISTS notify_processing BOOLEAN DEFAULT TRUE");
        DB::statement("ALTER TABLE wa_settings ADD COLUMN IF NOT EXISTS notify_ready BOOLEAN DEFAULT TRUE");
        DB::statement("ALTER TABLE wa_settings ADD COLUMN IF NOT EXISTS tpl_approved TEXT");
        DB::statement("ALTER TABLE wa_settings ADD COLUMN IF NOT EXISTS tpl_rejected TEXT");
        DB::statement("ALTER TABLE wa_settings ADD COLUMN IF NOT EXISTS tpl_processing TEXT");
        DB::statement("ALTER TABLE wa_settings ADD COLUMN IF NOT EXISTS tpl_ready_dinein TEXT");
        DB::statement("ALTER TABLE wa_settings ADD COLUMN IF NOT EXISTS tpl_ready_takeaway TEXT");
        DB::statement("ALTER TABLE wa_settings ADD COLUMN IF NOT EXISTS notify_completed BOOLEAN DEFAULT TRUE");
        DB::statement("ALTER TABLE wa_settings ADD COLUMN IF NOT EXISTS tpl_completed_dinein TEXT");
        DB::statement("ALTER TABLE wa_settings ADD COLUMN IF NOT EXISTS tpl_completed_takeaway TEXT");
    }
}
