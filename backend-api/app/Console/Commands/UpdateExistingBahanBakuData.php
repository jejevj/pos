<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateExistingBahanBakuData extends Command
{
    protected $signature = 'outlets:update-existing-bahan-baku';
    protected $description = 'Update existing bahan baku data with unit conversion examples';

    public function handle()
    {
        $outlets = DB::table('outlets')->get();

        foreach ($outlets as $outlet) {
            $this->info("Updating bahan baku data for outlet: {$outlet->name} (schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Get unit IDs
                $satuanKg = DB::table('satuan')->where('singkatan', 'kg')->first();
                $satuanLiter = DB::table('satuan')->where('singkatan', 'L')->first();
                
                if (!$satuanKg || !$satuanLiter) {
                    $this->warn("Required units not found for {$outlet->name}");
                    continue;
                }
                
                // Update Kopi Arabica
                $kopiArabica = DB::table('bahan_baku')->where('nama', 'Kopi Arabica')->first();
                if ($kopiArabica) {
                    DB::table('bahan_baku')
                        ->where('id', $kopiArabica->id)
                        ->update([
                            'satuan_pembelian_id' => $satuanKg->id,
                            'jumlah_per_unit_pembelian' => 1000, // 1 kg = 1000 gram
                            'harga_beli' => 150000.00, // Rp 150,000 per kg
                            'deskripsi' => 'Biji kopi arabica premium untuk espresso. Harga Rp 150,000/kg = Rp 150/gram',
                            'updated_at' => now(),
                        ]);
                    $this->info("  ✓ Updated Kopi Arabica");
                }
                
                // Update Air Mineral
                $airMineral = DB::table('bahan_baku')->where('nama', 'Air Mineral')->first();
                if ($airMineral) {
                    DB::table('bahan_baku')
                        ->where('id', $airMineral->id)
                        ->update([
                            'satuan_pembelian_id' => $satuanLiter->id,
                            'jumlah_per_unit_pembelian' => 20000, // 1 Galon = 20L = 20,000 ml
                            'harga_beli' => 3000.00, // Rp 3,000 per galon
                            'deskripsi' => 'Air mineral untuk minuman. Harga Rp 3,000/galon (20L) = Rp 0.15/ml',
                            'updated_at' => now(),
                        ]);
                    $this->info("  ✓ Updated Air Mineral");
                }
                
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
