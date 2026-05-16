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
                DB::statement("ALTER TABLE {$schema}.payment_methods ADD COLUMN IF NOT EXISTS qr_image_path VARCHAR(500) NULL");
            } catch (\Throwable $e) {
                \Log::warning("Backfill qr_image_path failed for outlet {$outlet->id}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // No-op; columns are additive and safe to keep.
    }
};
