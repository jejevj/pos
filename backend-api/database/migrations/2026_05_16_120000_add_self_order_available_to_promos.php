<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

/**
 * Adds `is_self_order_available` flag to per-outlet `promos` table.
 *
 * Safe default is FALSE — admins must explicitly opt promos in to self-order
 * visibility so existing internal promos do not leak to the public table /
 * takeaway checkout unintentionally.
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
                DB::statement("ALTER TABLE {$schema}.promos ADD COLUMN IF NOT EXISTS is_self_order_available BOOLEAN DEFAULT FALSE");
                DB::statement("CREATE INDEX IF NOT EXISTS idx_promos_self_order ON {$schema}.promos(is_self_order_available)");
            } catch (\Throwable $e) {
                \Log::warning("Backfill is_self_order_available failed for outlet {$outlet->id}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // No-op; column is additive and safe to keep.
    }
};
