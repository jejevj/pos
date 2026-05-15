<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

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
                DB::statement("ALTER TABLE {$schema}.tables ADD COLUMN IF NOT EXISTS qr_token VARCHAR(64) NULL");
                DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS idx_{$schema}_tables_qr_token ON {$schema}.tables(qr_token) WHERE qr_token IS NOT NULL");

                DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT 'pos'");
                DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS approval_status VARCHAR(20) NULL");
                DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS approved_by BIGINT NULL");
                DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL");
                DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS rejected_by BIGINT NULL");
                DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP NULL");
                DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL");
                DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS customer_email VARCHAR(255) NULL");
                DB::statement("CREATE INDEX IF NOT EXISTS idx_{$schema}_orders_source_approval ON {$schema}.orders(source, approval_status)");
                DB::statement("ALTER TABLE {$schema}.orders ALTER COLUMN cashier_id DROP NOT NULL");
            } catch (\Throwable $e) {
                \Log::warning("Backfill public-order columns failed for outlet {$outlet->id}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // No-op; columns are additive and safe to keep.
    }
};
