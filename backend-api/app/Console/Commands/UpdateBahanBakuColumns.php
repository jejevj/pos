<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateBahanBakuColumns extends Command
{
    protected $signature = 'outlets:update-bahan-baku-columns';
    protected $description = 'Add unit conversion columns to bahan_baku table in all outlet schemas';

    public function handle()
    {
        $outlets = DB::table('outlets')->get();

        foreach ($outlets as $outlet) {
            $this->info("Updating bahan_baku table for outlet: {$outlet->name} (schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                DB::statement('ALTER TABLE bahan_baku ADD COLUMN IF NOT EXISTS satuan_pembelian_id INTEGER REFERENCES satuan(id)');
                DB::statement('ALTER TABLE bahan_baku ADD COLUMN IF NOT EXISTS jumlah_per_unit_pembelian DECIMAL(10,4)');
                
                $this->info("✓ Successfully updated {$outlet->name}");
            } catch (\Exception $e) {
                $this->error("✗ Error updating {$outlet->name}: {$e->getMessage()}");
            }
        }
        
        DB::statement("SET search_path TO public");
        
        $this->info("\nUpdate completed!");
        
        return 0;
    }
}
