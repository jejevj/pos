<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

/**
 * Backfill `stock_opname_detail.stock_location_id` (and the supporting
 * location/stock-movement tables) into every existing outlet schema.
 *
 * Newly-created outlets already get these from OutletProvisioner. This
 * migration only exists so existing deployments pick up the column on a
 * routine `php artisan migrate --force` (which the backend entrypoint
 * runs automatically on every boot).
 */
return new class extends Migration
{
    public function up(): void
    {
        $outlets = Outlet::all();
        foreach ($outlets as $outlet) {
            $schema = $outlet->schema_name;
            if (!$schema) {
                continue;
            }
            try {
                DB::statement("CREATE TABLE IF NOT EXISTS {$schema}.locations (
                    id BIGSERIAL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    type VARCHAR(30) NOT NULL DEFAULT 'warehouse',
                    description TEXT,
                    is_active BOOLEAN DEFAULT TRUE,
                    display_order INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW(),
                    deleted_at TIMESTAMP NULL
                )");

                DB::statement("CREATE TABLE IF NOT EXISTS {$schema}.bahan_baku_locations (
                    id BIGSERIAL PRIMARY KEY,
                    bahan_baku_id BIGINT NOT NULL,
                    location_id BIGINT NOT NULL,
                    current_stock DECIMAL(12,4) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW(),
                    UNIQUE(bahan_baku_id, location_id)
                )");

                DB::statement("CREATE TABLE IF NOT EXISTS {$schema}.stock_movements (
                    id BIGSERIAL PRIMARY KEY,
                    bahan_baku_id BIGINT NOT NULL,
                    from_location_id BIGINT NULL,
                    to_location_id BIGINT NULL,
                    type VARCHAR(30) NOT NULL,
                    quantity DECIMAL(12,4) NOT NULL,
                    notes TEXT,
                    reference_type VARCHAR(50),
                    reference_id BIGINT,
                    created_by BIGINT,
                    created_at TIMESTAMP DEFAULT NOW()
                )");

                // The actual column the location-aware opname flow depends on.
                // stock_opname_detail itself exists on every outlet that was
                // provisioned with the original CreateStockOpnameTables — but
                // older ones may pre-date that, so guard both.
                DB::statement("ALTER TABLE IF EXISTS {$schema}.stock_opname_detail ADD COLUMN IF NOT EXISTS stock_location_id BIGINT");
                DB::statement("CREATE INDEX IF NOT EXISTS idx_{$schema}_stock_opname_detail_location ON {$schema}.stock_opname_detail(stock_location_id)");
            } catch (\Throwable $e) {
                \Log::warning("Backfill stock_location_id failed for outlet {$outlet->id}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // No-op; columns/tables are additive and safe to keep.
    }
};
