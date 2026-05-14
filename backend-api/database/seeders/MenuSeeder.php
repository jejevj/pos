<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder should be run AFTER BahanBakuSeeder
     * Usage: php artisan db:seed --class=MenuSeeder
     */
    public function run(): void
    {
        // Get all outlets
        $outlets = DB::table('outlets')->get();

        foreach ($outlets as $outlet) {
            echo "Seeding menu data for outlet: {$outlet->name} (schema: {$outlet->schema_name})\n";
            
            try {
                // Switch to outlet schema
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Seed Kategori Menu
                $this->seedKategoriMenu();
                
                // Seed Menu
                $this->seedMenu();
                
                echo "✓ Successfully seeded menu data for {$outlet->name}\n\n";
            } catch (\Exception $e) {
                echo "✗ Error seeding {$outlet->name}: {$e->getMessage()}\n\n";
            }
        }
        
        // Reset to public schema
        DB::statement("SET search_path TO public");
        
        echo "Menu seeding completed!\n";
    }

    private function seedKategoriMenu()
    {
        $categories = [
            ['nama' => 'Minuman', 'deskripsi' => 'Kategori minuman', 'urutan' => 1, 'is_active' => true],
            ['nama' => 'Makanan', 'deskripsi' => 'Kategori makanan', 'urutan' => 2, 'is_active' => true],
            ['nama' => 'Snack', 'deskripsi' => 'Kategori snack dan cemilan', 'urutan' => 3, 'is_active' => true],
        ];

        foreach ($categories as $category) {
            DB::table('kategori_menu')->insert(array_merge($category, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function seedMenu()
    {
        // Get kategori minuman
        $kategoriMinuman = DB::table('kategori_menu')->where('nama', 'Minuman')->first();
        
        if (!$kategoriMinuman) {
            echo "Warning: Kategori Minuman not found\n";
            return;
        }

        // Get bahan baku
        $kopiArabica = DB::table('bahan_baku')->where('nama', 'Kopi Arabica')->first();
        $airMineral = DB::table('bahan_baku')->where('nama', 'Air Mineral')->first();
        
        if (!$kopiArabica || !$airMineral) {
            echo "Warning: Required bahan baku not found\n";
            return;
        }

        // Get satuan
        $satuanGram = DB::table('satuan')->where('singkatan', 'g')->first();
        $satuanMl = DB::table('satuan')->where('singkatan', 'ml')->first();

        if (!$satuanGram || !$satuanMl) {
            echo "Warning: Required satuan not found\n";
            return;
        }

        // Create Americano menu
        $kode = 'MI' . date('Ymd') . '0001';
        
        // Calculate harga modal: (18g kopi * 150) + (200ml air * 0.01) = 2700 + 2 = 2702
        $hargaModal = (18 * 150) + (200 * 0.01);
        
        $menuId = DB::table('menu')->insertGetId([
            'kode' => $kode,
            'nama' => 'Americano',
            'kategori_id' => $kategoriMinuman->id,
            'deskripsi' => 'Espresso shot dengan air panas, menghasilkan kopi hitam yang kuat namun lebih ringan',
            'harga_jual' => 25000.00,
            'harga_modal' => $hargaModal,
            'gambar_url' => null,
            'is_available' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add bahan baku for Americano
        $bahanBakuItems = [
            [
                'menu_id' => $menuId,
                'bahan_baku_id' => $kopiArabica->id,
                'satuan_id' => $satuanGram->id,
                'jumlah' => 18.0000, // 18 gram kopi untuk double shot espresso
                'keterangan' => 'Double shot espresso',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'menu_id' => $menuId,
                'bahan_baku_id' => $airMineral->id,
                'satuan_id' => $satuanMl->id,
                'jumlah' => 200.0000, // 200 ml air panas
                'keterangan' => 'Air panas untuk mengencerkan espresso',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($bahanBakuItems as $item) {
            DB::table('menu_bahan_baku')->insert($item);
        }

        echo "  ✓ Created menu: Americano (Rp " . number_format(25000, 0, ',', '.') . ")\n";
        echo "    - Kopi Arabica: 18g\n";
        echo "    - Air Mineral: 200ml\n";
        echo "    - Harga Modal: Rp " . number_format($hargaModal, 0, ',', '.') . "\n";
    }
}
