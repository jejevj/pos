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
                // Payment-method flag: available for public/online ordering (table & takeaway).
                DB::statement("ALTER TABLE {$schema}.payment_methods ADD COLUMN IF NOT EXISTS is_online_orderable BOOLEAN DEFAULT FALSE");
                // Default QRIS to true (safe: it is the typical online-payment instrument).
                DB::statement("UPDATE {$schema}.payment_methods SET is_online_orderable = TRUE WHERE code = 'qris' AND is_online_orderable = FALSE");

                // Order columns: payment proof from public customers and takeaway order_type.
                DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS payment_proof_path VARCHAR(500) NULL");
                DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS payment_proof_uploaded_at TIMESTAMP NULL");

                // Drop NOT NULL on order_type so we can use 'takeaway' too (it was always
                // populated by POS; this just relaxes the constraint for clarity).
                // (No actual change to type required — it's VARCHAR(20).)
            } catch (\Throwable $e) {
                \Log::warning("Backfill online-order columns failed for outlet {$outlet->id}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // No-op; columns are additive and safe to keep.
    }
};
