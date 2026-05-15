<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionSettingController extends Controller
{
    use AuthorizesOutletAccess;

    /**
     * Tabel: transaction_settings (1 baris per outlet schema)
     * Kolom:
     *   tax_enabled          BOOLEAN  — apakah PPN aktif
     *   tax_percentage       DECIMAL  — persentase PPN (default 11)
     *   tax_label            VARCHAR  — label tampilan (default 'PPN')
     *   tax_inclusive        BOOLEAN  — harga sudah termasuk PPN (inclusive) atau belum (exclusive)
     *   service_charge_enabled     BOOLEAN
     *   service_charge_percentage  DECIMAL
     *   service_charge_label       VARCHAR
     *   receipt_footer       TEXT     — catatan di bawah struk
     *   min_order_amount     DECIMAL  — minimal order
     */

    private function ensureTable(string $schema): void
    {
        $exists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = ? AND table_name = 'transaction_settings'
            ) AS ex
        ", [$schema]);

        if (!$exists->ex) {
            DB::statement("
                CREATE TABLE {$schema}.transaction_settings (
                    id                          SERIAL PRIMARY KEY,
                    -- PPN
                    tax_enabled                 BOOLEAN DEFAULT true,
                    tax_percentage              DECIMAL(5,2) DEFAULT 11,
                    tax_label                   VARCHAR(50) DEFAULT 'PPN',
                    tax_inclusive               BOOLEAN DEFAULT false,
                    -- Service charge
                    service_charge_enabled      BOOLEAN DEFAULT false,
                    service_charge_percentage   DECIMAL(5,2) DEFAULT 0,
                    service_charge_label        VARCHAR(50) DEFAULT 'Service Charge',
                    -- Aturan lain
                    min_order_amount            DECIMAL(15,2) DEFAULT 0,
                    -- Kustomisasi struk
                    receipt_logo_enabled        BOOLEAN DEFAULT true,
                    receipt_header              TEXT DEFAULT '',
                    receipt_footer              TEXT DEFAULT '',
                    receipt_show_qr             BOOLEAN DEFAULT true,
                    receipt_wifi_enabled        BOOLEAN DEFAULT false,
                    receipt_wifi_ssid           VARCHAR(100) DEFAULT '',
                    receipt_wifi_password       VARCHAR(100) DEFAULT '',
                    receipt_show_cashier        BOOLEAN DEFAULT true,
                    receipt_show_table          BOOLEAN DEFAULT true,
                    receipt_show_member         BOOLEAN DEFAULT true,
                    receipt_custom_logo_url     TEXT DEFAULT '',
                    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            DB::statement("
                INSERT INTO {$schema}.transaction_settings
                    (tax_enabled, tax_percentage, tax_label, tax_inclusive,
                     service_charge_enabled, service_charge_percentage, service_charge_label,
                     min_order_amount,
                     receipt_logo_enabled, receipt_header, receipt_footer,
                     receipt_show_qr, receipt_wifi_enabled, receipt_wifi_ssid, receipt_wifi_password,
                     receipt_show_cashier, receipt_show_table, receipt_show_member, receipt_custom_logo_url)
                VALUES (true, 11, 'PPN', false, false, 0, 'Service Charge', 0,
                        true, '', '', true, false, '', '', true, true, true, '')
            ");
        } else {
            // Migrasi kolom baru jika tabel sudah ada tapi kolom belum ada
            $this->addColumnIfMissing($schema, 'receipt_logo_enabled',    'BOOLEAN DEFAULT true');
            $this->addColumnIfMissing($schema, 'receipt_header',          "TEXT DEFAULT ''");
            $this->addColumnIfMissing($schema, 'receipt_show_qr',         'BOOLEAN DEFAULT true');
            $this->addColumnIfMissing($schema, 'receipt_wifi_enabled',    'BOOLEAN DEFAULT false');
            $this->addColumnIfMissing($schema, 'receipt_wifi_ssid',       "VARCHAR(100) DEFAULT ''");
            $this->addColumnIfMissing($schema, 'receipt_wifi_password',   "VARCHAR(100) DEFAULT ''");
            $this->addColumnIfMissing($schema, 'receipt_show_cashier',    'BOOLEAN DEFAULT true');
            $this->addColumnIfMissing($schema, 'receipt_show_table',      'BOOLEAN DEFAULT true');
            $this->addColumnIfMissing($schema, 'receipt_show_member',     'BOOLEAN DEFAULT true');
            $this->addColumnIfMissing($schema, 'receipt_custom_logo_url', "TEXT DEFAULT ''");
            // Pastikan receipt_footer ada (kolom lama)
            $this->addColumnIfMissing($schema, 'receipt_footer',          "TEXT DEFAULT ''");
            $this->addColumnIfMissing($schema, 'min_order_amount',        'DECIMAL(15,2) DEFAULT 0');
        }
    }

    private function addColumnIfMissing(string $schema, string $column, string $definition): void
    {
        $exists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = ? AND table_name = 'transaction_settings' AND column_name = ?
            ) AS ex
        ", [$schema, $column]);
        if (!$exists->ex) {
            DB::statement("ALTER TABLE {$schema}.transaction_settings ADD COLUMN {$column} {$definition}");
        }
    }

    /** GET /outlets/{outlet}/transaction-settings */
    public function index($outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            $this->ensureTable($outlet->schema_name);

            $settings = DB::table("{$outlet->schema_name}.transaction_settings")->first();

            return response()->json($settings);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /** PUT /outlets/{outlet}/transaction-settings */
    public function update(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            'tax_enabled'                => 'nullable|boolean',
            'tax_percentage'             => 'nullable|numeric|min:0|max:100',
            'tax_label'                  => 'nullable|string|max:50',
            'tax_inclusive'              => 'nullable|boolean',
            'service_charge_enabled'     => 'nullable|boolean',
            'service_charge_percentage'  => 'nullable|numeric|min:0|max:100',
            'service_charge_label'       => 'nullable|string|max:50',
            'min_order_amount'           => 'nullable|numeric|min:0',
            // Struk
            'receipt_logo_enabled'       => 'nullable|boolean',
            'receipt_header'             => 'nullable|string|max:500',
            'receipt_footer'             => 'nullable|string|max:500',
            'receipt_show_qr'            => 'nullable|boolean',
            'receipt_wifi_enabled'       => 'nullable|boolean',
            'receipt_wifi_ssid'          => 'nullable|string|max:100',
            'receipt_wifi_password'      => 'nullable|string|max:100',
            'receipt_show_cashier'       => 'nullable|boolean',
            'receipt_show_table'         => 'nullable|boolean',
            'receipt_show_member'        => 'nullable|boolean',
            'receipt_custom_logo_url'    => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $this->ensureTable($outlet->schema_name);

            // Helper untuk boolean yang mungkin dikirim sebagai string
            $bool = fn($key) => $request->has($key) ? (bool) $request->input($key) : null;

            // Hanya update kolom yang dikirim
            $data = array_filter([
                'tax_enabled'               => $bool('tax_enabled'),
                'tax_percentage'            => $request->tax_percentage,
                'tax_label'                 => $request->tax_label,
                'tax_inclusive'             => $bool('tax_inclusive'),
                'service_charge_enabled'    => $bool('service_charge_enabled'),
                'service_charge_percentage' => $request->service_charge_percentage,
                'service_charge_label'      => $request->service_charge_label,
                'min_order_amount'          => $request->min_order_amount,
                // Struk
                'receipt_logo_enabled'      => $bool('receipt_logo_enabled'),
                'receipt_header'            => $request->receipt_header,
                'receipt_footer'            => $request->receipt_footer,
                'receipt_show_qr'           => $bool('receipt_show_qr'),
                'receipt_wifi_enabled'      => $bool('receipt_wifi_enabled'),
                'receipt_wifi_ssid'         => $request->receipt_wifi_ssid,
                'receipt_wifi_password'     => $request->receipt_wifi_password,
                'receipt_show_cashier'      => $bool('receipt_show_cashier'),
                'receipt_show_table'        => $bool('receipt_show_table'),
                'receipt_show_member'       => $bool('receipt_show_member'),
                'receipt_custom_logo_url'   => $request->receipt_custom_logo_url,
                'updated_at'                => now(),
            ], fn($v) => $v !== null);

            DB::table("{$outlet->schema_name}.transaction_settings")->update($data);

            $settings = DB::table("{$outlet->schema_name}.transaction_settings")->first();

            return response()->json([
                'message' => 'Pengaturan transaksi berhasil disimpan',
                'data'    => $settings,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
