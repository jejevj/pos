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
                    tax_enabled                 BOOLEAN DEFAULT true,
                    tax_percentage              DECIMAL(5,2) DEFAULT 11,
                    tax_label                   VARCHAR(50) DEFAULT 'PPN',
                    tax_inclusive               BOOLEAN DEFAULT false,
                    service_charge_enabled      BOOLEAN DEFAULT false,
                    service_charge_percentage   DECIMAL(5,2) DEFAULT 0,
                    service_charge_label        VARCHAR(50) DEFAULT 'Service Charge',
                    receipt_footer              TEXT DEFAULT '',
                    min_order_amount            DECIMAL(15,2) DEFAULT 0,
                    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            DB::statement("
                INSERT INTO {$schema}.transaction_settings
                    (tax_enabled, tax_percentage, tax_label, tax_inclusive,
                     service_charge_enabled, service_charge_percentage, service_charge_label,
                     receipt_footer, min_order_amount)
                VALUES (true, 11, 'PPN', false, false, 0, 'Service Charge', '', 0)
            ");
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
            'receipt_footer'             => 'nullable|string|max:500',
            'min_order_amount'           => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $this->ensureTable($outlet->schema_name);

            // Hanya update kolom yang dikirim (tidak null)
            $data = array_filter([
                'tax_enabled'               => $request->has('tax_enabled')            ? (bool) $request->tax_enabled            : null,
                'tax_percentage'            => $request->tax_percentage,
                'tax_label'                 => $request->tax_label,
                'tax_inclusive'             => $request->has('tax_inclusive')           ? (bool) $request->tax_inclusive           : null,
                'service_charge_enabled'    => $request->has('service_charge_enabled')  ? (bool) $request->service_charge_enabled  : null,
                'service_charge_percentage' => $request->service_charge_percentage,
                'service_charge_label'      => $request->service_charge_label,
                'receipt_footer'            => $request->receipt_footer,
                'min_order_amount'          => $request->min_order_amount,
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
