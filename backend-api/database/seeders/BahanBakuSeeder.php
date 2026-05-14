<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BahanBakuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder should be run AFTER outlet schemas are created
     * Usage: php artisan db:seed --class=BahanBakuSeeder
     */
    public function run(): void
    {
        // Get all outlets
        $outlets = DB::table('outlets')->get();

        foreach ($outlets as $outlet) {
            echo "Seeding bahan baku data for outlet: {$outlet->name} (schema: {$outlet->schema_name})\n";
            
            try {
                // Switch to outlet schema
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Seed Kategori Bahan Baku
                $this->seedKategori();
                
                // Seed Satuan
                $this->seedSatuan();
                
                // Seed Bahan Baku
                $this->seedBahanBaku();
                
                echo "✓ Successfully seeded data for {$outlet->name}\n\n";
            } catch (\Exception $e) {
                echo "✗ Error seeding {$outlet->name}: {$e->getMessage()}\n\n";
            }
        }
        
        // Reset to public schema
        DB::statement("SET search_path TO public");
        
        echo "Bahan Baku seeding completed!\n";
    }

    private function seedKategori()
    {
        $categories = [
            ['nama' => 'Sayuran', 'deskripsi' => 'Bahan baku sayuran segar', 'is_active' => true],
            ['nama' => 'Buah-buahan', 'deskripsi' => 'Bahan baku buah segar', 'is_active' => true],
            ['nama' => 'Daging', 'deskripsi' => 'Daging sapi, ayam, kambing, dll', 'is_active' => true],
            ['nama' => 'Seafood', 'deskripsi' => 'Ikan, udang, cumi, dll', 'is_active' => true],
            ['nama' => 'Bumbu & Rempah', 'deskripsi' => 'Bumbu dapur dan rempah-rempah', 'is_active' => true],
            ['nama' => 'Minyak & Lemak', 'deskripsi' => 'Minyak goreng, mentega, margarin', 'is_active' => true],
            ['nama' => 'Tepung & Biji-bijian', 'deskripsi' => 'Tepung terigu, beras, dll', 'is_active' => true],
            ['nama' => 'Susu & Produk Olahan', 'deskripsi' => 'Susu, keju, yogurt, dll', 'is_active' => true],
            ['nama' => 'Minuman', 'deskripsi' => 'Kopi, teh, sirup, dll', 'is_active' => true],
            ['nama' => 'Kemasan & Packaging', 'deskripsi' => 'Box, plastik, kertas, dll', 'is_active' => true],
        ];

        foreach ($categories as $category) {
            DB::table('kategori_bahan_baku')->insert(array_merge($category, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function seedSatuan()
    {
        // Base units (weight)
        $baseUnits = [
            ['nama' => 'Gram', 'singkatan' => 'g', 'tipe' => 'weight', 'is_base_unit' => true, 'conversion_to_base' => null, 'deskripsi' => 'Satuan dasar berat', 'is_active' => true],
            ['nama' => 'Mililiter', 'singkatan' => 'ml', 'tipe' => 'volume', 'is_base_unit' => true, 'conversion_to_base' => null, 'deskripsi' => 'Satuan dasar volume', 'is_active' => true],
            ['nama' => 'Pieces', 'singkatan' => 'pcs', 'tipe' => 'count', 'is_base_unit' => true, 'conversion_to_base' => null, 'deskripsi' => 'Satuan hitung', 'is_active' => true],
        ];

        foreach ($baseUnits as $unit) {
            DB::table('satuan')->insert(array_merge($unit, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Derived units (with conversion to base)
        $derivedUnits = [
            // Weight units (base: gram)
            ['nama' => 'Kilogram', 'singkatan' => 'kg', 'tipe' => 'weight', 'is_base_unit' => false, 'conversion_to_base' => 1000, 'deskripsi' => '1 kg = 1000 gram', 'is_active' => true],
            ['nama' => 'Ons', 'singkatan' => 'ons', 'tipe' => 'weight', 'is_base_unit' => false, 'conversion_to_base' => 100, 'deskripsi' => '1 ons = 100 gram', 'is_active' => true],
            
            // Volume units (base: mililiter)
            ['nama' => 'Liter', 'singkatan' => 'L', 'tipe' => 'volume', 'is_base_unit' => false, 'conversion_to_base' => 1000, 'deskripsi' => '1 L = 1000 ml', 'is_active' => true],
            ['nama' => 'Galon', 'singkatan' => 'gal', 'tipe' => 'volume', 'is_base_unit' => false, 'conversion_to_base' => 3785, 'deskripsi' => '1 galon = 3785 ml', 'is_active' => true],
            
            // Count units
            ['nama' => 'Lusin', 'singkatan' => 'lusin', 'tipe' => 'count', 'is_base_unit' => false, 'conversion_to_base' => 12, 'deskripsi' => '1 lusin = 12 pcs', 'is_active' => true],
            ['nama' => 'Pack', 'singkatan' => 'pack', 'tipe' => 'count', 'is_base_unit' => false, 'conversion_to_base' => null, 'deskripsi' => 'Kemasan pack', 'is_active' => true],
            ['nama' => 'Box', 'singkatan' => 'box', 'tipe' => 'count', 'is_base_unit' => false, 'conversion_to_base' => null, 'deskripsi' => 'Kemasan box', 'is_active' => true],
            ['nama' => 'Karton', 'singkatan' => 'karton', 'tipe' => 'count', 'is_base_unit' => false, 'conversion_to_base' => null, 'deskripsi' => 'Kemasan karton', 'is_active' => true],
        ];

        foreach ($derivedUnits as $unit) {
            DB::table('satuan')->insert(array_merge($unit, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function seedBahanBaku()
    {
        // Get IDs for categories and units
        $kategoriMinuman = DB::table('kategori_bahan_baku')->where('nama', 'Minuman')->first();
        $satuanGram = DB::table('satuan')->where('singkatan', 'g')->first();
        $satuanMl = DB::table('satuan')->where('singkatan', 'ml')->first();
        $satuanKg = DB::table('satuan')->where('singkatan', 'kg')->first();
        $satuanLiter = DB::table('satuan')->where('singkatan', 'L')->first();

        if (!$kategoriMinuman || !$satuanGram || !$satuanMl || !$satuanKg || !$satuanLiter) {
            echo "Warning: Required categories or units not found\n";
            return;
        }

        $bahanBaku = [
            [
                'kode' => 'MI' . date('Ymd') . '0001',
                'nama' => 'Kopi Arabica',
                'kategori_id' => $kategoriMinuman->id,
                'satuan_id' => $satuanGram->id,
                'satuan_pembelian_id' => $satuanKg->id,
                'jumlah_per_unit_pembelian' => 1000, // 1 kg = 1000 gram
                'supplier_id' => null,
                'harga_beli' => 150000.00, // Rp 150,000 per kg
                'minimum_stock' => 1000, // 1 kg
                'current_stock' => 5000, // 5 kg
                'lokasi_penyimpanan' => 'Rak Minuman A1',
                'expired_date' => null,
                'gambar_url' => null,
                'deskripsi' => 'Biji kopi arabica premium untuk espresso. Harga Rp 150,000/kg = Rp 150/gram',
                'is_active' => true,
            ],
            [
                'kode' => 'MI' . date('Ymd') . '0002',
                'nama' => 'Air Mineral',
                'kategori_id' => $kategoriMinuman->id,
                'satuan_id' => $satuanMl->id,
                'satuan_pembelian_id' => $satuanLiter->id,
                'jumlah_per_unit_pembelian' => 20000, // 1 Galon = 20 Liter = 20,000 ml
                'supplier_id' => null,
                'harga_beli' => 3000.00, // Rp 3,000 per galon (20L)
                'minimum_stock' => 10000, // 10 liter
                'current_stock' => 50000, // 50 liter
                'lokasi_penyimpanan' => 'Dispenser',
                'expired_date' => null,
                'gambar_url' => null,
                'deskripsi' => 'Air mineral untuk minuman. Harga Rp 3,000/galon (20L) = Rp 0.15/ml',
                'is_active' => true,
            ],
        ];

        foreach ($bahanBaku as $bahan) {
            DB::table('bahan_baku')->insert(array_merge($bahan, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
