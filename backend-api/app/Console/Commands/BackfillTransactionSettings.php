<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillTransactionSettings extends Command
{
    protected $signature = 'pos:backfill-transaction-settings';
    protected $description = 'Ensure transaction_settings table exists and is healed for all outlet schemas';

    public function handle(): int
    {
        $outlets = Outlet::all();

        if ($outlets->isEmpty()) {
            $this->warn('No outlets found.');
            return 0;
        }

        foreach ($outlets as $outlet) {
            $schema = $outlet->schema_name;
            $this->info("Processing outlet: {$outlet->nama} (schema: {$schema})");

            try {
                DB::statement("SET search_path TO {$schema}, public");

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
                            min_order_amount            DECIMAL(15,2) DEFAULT 0,
                            receipt_logo_enabled        BOOLEAN DEFAULT true,
                            receipt_custom_logo_url     TEXT DEFAULT '',
                            receipt_header              TEXT DEFAULT '',
                            receipt_footer              TEXT DEFAULT '',
                            receipt_show_qr             BOOLEAN DEFAULT true,
                            receipt_wifi_enabled        BOOLEAN DEFAULT false,
                            receipt_wifi_ssid           VARCHAR(100) DEFAULT '',
                            receipt_wifi_password       VARCHAR(100) DEFAULT '',
                            receipt_show_cashier        BOOLEAN DEFAULT true,
                            receipt_show_table          BOOLEAN DEFAULT true,
                            receipt_show_member         BOOLEAN DEFAULT true,
                            created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");

                    DB::statement("
                        INSERT INTO {$schema}.transaction_settings
                            (tax_enabled, tax_percentage, tax_label, tax_inclusive,
                             service_charge_enabled, service_charge_percentage, service_charge_label,
                             min_order_amount,
                             receipt_logo_enabled, receipt_custom_logo_url,
                             receipt_header, receipt_footer,
                             receipt_show_qr, receipt_wifi_enabled, receipt_wifi_ssid, receipt_wifi_password,
                             receipt_show_cashier, receipt_show_table, receipt_show_member)
                        VALUES (true, 11, 'PPN', false, false, 0, 'Service Charge', 0,
                                true, '', '', '', true, false, '', '', true, true, true)
                    ");

                    $this->info("  ✓ Created transaction_settings for schema: {$schema}");
                } else {
                    $this->healColumns($schema);

                    $count = DB::table("{$schema}.transaction_settings")->count();
                    if ($count === 0) {
                        DB::statement("
                            INSERT INTO {$schema}.transaction_settings
                                (tax_enabled, tax_percentage, tax_label)
                            VALUES (true, 11, 'PPN')
                        ");
                        $this->info("  ✓ Inserted default row for schema: {$schema}");
                    }

                    $this->info("  ✓ Healed transaction_settings for schema: {$schema}");
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Failed for schema {$schema}: " . $e->getMessage());
            } finally {
                DB::statement("SET search_path TO public");
            }
        }

        $this->info('Done.');
        return 0;
    }

    private function healColumns(string $schema): void
    {
        $columns = [
            'tax_enabled'               => 'BOOLEAN DEFAULT true',
            'tax_percentage'            => 'DECIMAL(5,2) DEFAULT 11',
            'tax_label'                 => "VARCHAR(50) DEFAULT 'PPN'",
            'tax_inclusive'             => 'BOOLEAN DEFAULT false',
            'service_charge_enabled'    => 'BOOLEAN DEFAULT false',
            'service_charge_percentage' => 'DECIMAL(5,2) DEFAULT 0',
            'service_charge_label'      => "VARCHAR(50) DEFAULT 'Service Charge'",
            'min_order_amount'          => 'DECIMAL(15,2) DEFAULT 0',
            'receipt_logo_enabled'      => 'BOOLEAN DEFAULT true',
            'receipt_custom_logo_url'   => "TEXT DEFAULT ''",
            'receipt_header'            => "TEXT DEFAULT ''",
            'receipt_footer'            => "TEXT DEFAULT ''",
            'receipt_show_qr'           => 'BOOLEAN DEFAULT true',
            'receipt_wifi_enabled'      => 'BOOLEAN DEFAULT false',
            'receipt_wifi_ssid'         => "VARCHAR(100) DEFAULT ''",
            'receipt_wifi_password'     => "VARCHAR(100) DEFAULT ''",
            'receipt_show_cashier'      => 'BOOLEAN DEFAULT true',
            'receipt_show_table'        => 'BOOLEAN DEFAULT true',
            'receipt_show_member'       => 'BOOLEAN DEFAULT true',
        ];

        foreach ($columns as $col => $def) {
            DB::statement("ALTER TABLE {$schema}.transaction_settings ADD COLUMN IF NOT EXISTS {$col} {$def}");
        }
    }
}
