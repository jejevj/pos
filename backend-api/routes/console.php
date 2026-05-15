<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pos:backfill-transaction-settings', function () {
    $outlets = DB::table('outlets')->get();
    if ($outlets->isEmpty()) {
        $this->warn('No outlets found.');
        return;
    }
    foreach ($outlets as $outlet) {
        $schema = $outlet->schema_name ?? null;
        if (!$schema) {
            $this->warn("Outlet ID {$outlet->id} has no schema_name, skipping.");
            continue;
        }
        try {
            DB::statement("SET search_path TO \"{$schema}\", public");
            $hasTable = DB::select("SELECT to_regclass('\"{$schema}\".transaction_settings') AS t")[0]->t ?? null;
            if (!$hasTable) {
                DB::statement("CREATE TABLE transaction_settings (
                    id BIGSERIAL PRIMARY KEY,
                    tax_enabled BOOLEAN NOT NULL DEFAULT true,
                    tax_percentage NUMERIC(5,2) NOT NULL DEFAULT 11,
                    tax_label VARCHAR(50) NOT NULL DEFAULT 'PPN',
                    tax_inclusive BOOLEAN NOT NULL DEFAULT false,
                    service_charge_enabled BOOLEAN NOT NULL DEFAULT false,
                    service_charge_percentage NUMERIC(5,2) NOT NULL DEFAULT 0,
                    service_charge_label VARCHAR(50) NOT NULL DEFAULT 'Service Charge',
                    min_order_amount NUMERIC(12,2) NOT NULL DEFAULT 0,
                    receipt_logo_enabled BOOLEAN NOT NULL DEFAULT true,
                    receipt_custom_logo_url TEXT NOT NULL DEFAULT '',
                    receipt_header TEXT NOT NULL DEFAULT '',
                    receipt_footer TEXT NOT NULL DEFAULT '',
                    receipt_show_qr BOOLEAN NOT NULL DEFAULT true,
                    receipt_wifi_enabled BOOLEAN NOT NULL DEFAULT false,
                    receipt_wifi_ssid VARCHAR(100) NOT NULL DEFAULT '',
                    receipt_wifi_password VARCHAR(100) NOT NULL DEFAULT '',
                    receipt_show_cashier BOOLEAN NOT NULL DEFAULT true,
                    receipt_show_table BOOLEAN NOT NULL DEFAULT true,
                    receipt_show_member BOOLEAN NOT NULL DEFAULT true,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW()
                )");
                DB::statement("SET search_path TO \"{$schema}\", public");
                DB::table('transaction_settings')->insert([
                    'tax_enabled' => true, 'tax_percentage' => 11, 'tax_label' => 'PPN',
                    'tax_inclusive' => false, 'service_charge_enabled' => false,
                    'service_charge_percentage' => 0, 'service_charge_label' => 'Service Charge',
                    'min_order_amount' => 0, 'receipt_logo_enabled' => true,
                    'receipt_custom_logo_url' => '', 'receipt_header' => '', 'receipt_footer' => '',
                    'receipt_show_qr' => true, 'receipt_wifi_enabled' => false,
                    'receipt_wifi_ssid' => '', 'receipt_wifi_password' => '',
                    'receipt_show_cashier' => true, 'receipt_show_table' => true, 'receipt_show_member' => true,
                ]);
                $this->info("[OK] Created: {$outlet->name} (schema: {$schema})");
            } else {
                $cols = [
                    'tax_enabled' => 'BOOLEAN NOT NULL DEFAULT true',
                    'tax_percentage' => 'NUMERIC(5,2) NOT NULL DEFAULT 11',
                    'tax_label' => "VARCHAR(50) NOT NULL DEFAULT 'PPN'",
                    'tax_inclusive' => 'BOOLEAN NOT NULL DEFAULT false',
                    'service_charge_enabled' => 'BOOLEAN NOT NULL DEFAULT false',
                    'service_charge_percentage' => 'NUMERIC(5,2) NOT NULL DEFAULT 0',
                    'service_charge_label' => "VARCHAR(50) NOT NULL DEFAULT 'Service Charge'",
                    'min_order_amount' => 'NUMERIC(12,2) NOT NULL DEFAULT 0',
                    'receipt_logo_enabled' => 'BOOLEAN NOT NULL DEFAULT true',
                    'receipt_custom_logo_url' => "TEXT NOT NULL DEFAULT ''",
                    'receipt_header' => "TEXT NOT NULL DEFAULT ''",
                    'receipt_footer' => "TEXT NOT NULL DEFAULT ''",
                    'receipt_show_qr' => 'BOOLEAN NOT NULL DEFAULT true',
                    'receipt_wifi_enabled' => 'BOOLEAN NOT NULL DEFAULT false',
                    'receipt_wifi_ssid' => "VARCHAR(100) NOT NULL DEFAULT ''",
                    'receipt_wifi_password' => "VARCHAR(100) NOT NULL DEFAULT ''",
                    'receipt_show_cashier' => 'BOOLEAN NOT NULL DEFAULT true',
                    'receipt_show_table' => 'BOOLEAN NOT NULL DEFAULT true',
                    'receipt_show_member' => 'BOOLEAN NOT NULL DEFAULT true',
                ];
                foreach ($cols as $col => $def) {
                    DB::statement("ALTER TABLE \"{$schema}\".transaction_settings ADD COLUMN IF NOT EXISTS {$col} {$def}");
                }
                $count = DB::table('transaction_settings')->count();
                if ($count === 0) {
                    DB::table('transaction_settings')->insert(['tax_enabled' => true, 'tax_percentage' => 11, 'tax_label' => 'PPN']);
                }
                $this->info("[OK] Healed: {$outlet->name} (schema: {$schema})");
            }
        } catch (\Throwable $e) {
            $this->error("[FAIL] {$outlet->name}: " . $e->getMessage());
        }
    }
    DB::statement("SET search_path TO public");
    $this->info('Backfill complete.');
})->purpose('Ensure transaction_settings for all existing outlets');
