<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seeds default master data for a newly provisioned outlet schema.
 *
 * Data yang di-seed:
 *  - Satuan (weight, volume, count) — base + turunan
 *  - Kategori Bahan Baku
 *  - Bahan Baku (bahan dasar kopi, susu, tepung, dll)
 *  - Kategori Menu (Makanan & Minuman)
 *  - Menu (3 makanan + 3 minuman) lengkap dengan menu_bahan_baku
 *
 * Idempotent: cek apakah data sudah ada sebelum insert,
 * sehingga aman dipanggil berulang kali.
 */
class OutletDefaultDataSeeder
{
    private string $schema;

    public function seed(string $schema): void
    {
        $this->schema = $schema;

        try {
            DB::statement("SET search_path TO {$schema}, public");

            // Skip jika sudah ada data (idempotent guard)
            $existingSatuan = DB::table('satuan')->count();
            if ($existingSatuan > 0) {
                Log::info("OutletDefaultDataSeeder: schema {$schema} already seeded, skipping.");
                return;
            }

            $this->seedSatuan();
            $this->seedKategoriBahanBaku();
            $this->seedBahanBaku();
            $this->seedKategoriMenu();
            $this->seedMenu();

            Log::info("OutletDefaultDataSeeder: schema {$schema} seeded successfully.");
        } catch (\Throwable $e) {
            Log::error("OutletDefaultDataSeeder failed for schema {$schema}: " . $e->getMessage());
            // Tidak throw — provisioner tetap lanjut, data awal bisa di-seed manual
        } finally {
            DB::statement('SET search_path TO public');
        }
    }

    // ─────────────────────────────────────────────
    //  SATUAN
    // ─────────────────────────────────────────────

    private function seedSatuan(): void
    {
        $now = now();

        // Satuan dasar (base units)
        $baseUnits = [
            ['nama' => 'Gram',      'singkatan' => 'g',   'tipe' => 'weight', 'is_base_unit' => true,  'conversion_to_base' => null,   'deskripsi' => 'Satuan dasar berat'],
            ['nama' => 'Mililiter', 'singkatan' => 'ml',  'tipe' => 'volume', 'is_base_unit' => true,  'conversion_to_base' => null,   'deskripsi' => 'Satuan dasar volume'],
            ['nama' => 'Pieces',    'singkatan' => 'pcs', 'tipe' => 'count',  'is_base_unit' => true,  'conversion_to_base' => null,   'deskripsi' => 'Satuan hitung satuan'],
        ];

        // Satuan turunan
        $derivedUnits = [
            ['nama' => 'Kilogram',  'singkatan' => 'kg',    'tipe' => 'weight', 'is_base_unit' => false, 'conversion_to_base' => 1000,   'deskripsi' => '1 kg = 1000 gram'],
            ['nama' => 'Ons',       'singkatan' => 'ons',   'tipe' => 'weight', 'is_base_unit' => false, 'conversion_to_base' => 100,    'deskripsi' => '1 ons = 100 gram'],
            ['nama' => 'Liter',     'singkatan' => 'L',     'tipe' => 'volume', 'is_base_unit' => false, 'conversion_to_base' => 1000,   'deskripsi' => '1 L = 1000 ml'],
            ['nama' => 'Centiliter','singkatan' => 'cl',    'tipe' => 'volume', 'is_base_unit' => false, 'conversion_to_base' => 10,     'deskripsi' => '1 cl = 10 ml'],
            ['nama' => 'Lusin',     'singkatan' => 'lusin', 'tipe' => 'count',  'is_base_unit' => false, 'conversion_to_base' => 12,     'deskripsi' => '1 lusin = 12 pcs'],
            ['nama' => 'Pack',      'singkatan' => 'pack',  'tipe' => 'count',  'is_base_unit' => false, 'conversion_to_base' => null,   'deskripsi' => 'Kemasan pack'],
            ['nama' => 'Box',       'singkatan' => 'box',   'tipe' => 'count',  'is_base_unit' => false, 'conversion_to_base' => null,   'deskripsi' => 'Kemasan box'],
        ];

        foreach (array_merge($baseUnits, $derivedUnits) as $unit) {
            DB::table('satuan')->insert(array_merge($unit, [
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    // ─────────────────────────────────────────────
    //  KATEGORI BAHAN BAKU
    // ─────────────────────────────────────────────

    private function seedKategoriBahanBaku(): void
    {
        $now = now();
        $categories = [
            ['nama' => 'Biji Kopi & Teh',     'deskripsi' => 'Kopi arabica, robusta, teh, matcha dll'],
            ['nama' => 'Susu & Dairy',         'deskripsi' => 'Susu segar, susu UHT, krim, keju dll'],
            ['nama' => 'Sirup & Perasa',       'deskripsi' => 'Sirup gula, vanilla, karamel, coklat dll'],
            ['nama' => 'Tepung & Biji-bijian', 'deskripsi' => 'Tepung terigu, beras, oat dll'],
            ['nama' => 'Daging & Protein',     'deskripsi' => 'Ayam, daging sapi, telur dll'],
            ['nama' => 'Sayuran & Buah',       'deskripsi' => 'Sayuran segar, buah-buahan'],
            ['nama' => 'Bumbu & Rempah',       'deskripsi' => 'Garam, gula, merica, bumbu masak'],
            ['nama' => 'Minyak & Lemak',       'deskripsi' => 'Minyak goreng, mentega, margarin'],
            ['nama' => 'Kemasan',              'deskripsi' => 'Cup, sedotan, box makanan, kantong'],
        ];

        foreach ($categories as $cat) {
            DB::table('kategori_bahan_baku')->insert(array_merge($cat, [
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    // ─────────────────────────────────────────────
    //  BAHAN BAKU
    // ─────────────────────────────────────────────

    private function seedBahanBaku(): void
    {
        // Ambil satuan
        $g      = DB::table('satuan')->where('singkatan', 'g')->first();
        $ml     = DB::table('satuan')->where('singkatan', 'ml')->first();
        $kg     = DB::table('satuan')->where('singkatan', 'kg')->first();
        $L      = DB::table('satuan')->where('singkatan', 'L')->first();
        $pcs    = DB::table('satuan')->where('singkatan', 'pcs')->first();
        $pack   = DB::table('satuan')->where('singkatan', 'pack')->first();

        // Ambil kategori
        $katKopi    = DB::table('kategori_bahan_baku')->where('nama', 'Biji Kopi & Teh')->first();
        $katSusu    = DB::table('kategori_bahan_baku')->where('nama', 'Susu & Dairy')->first();
        $katSirup   = DB::table('kategori_bahan_baku')->where('nama', 'Sirup & Perasa')->first();
        $katTepung  = DB::table('kategori_bahan_baku')->where('nama', 'Tepung & Biji-bijian')->first();
        $katDaging  = DB::table('kategori_bahan_baku')->where('nama', 'Daging & Protein')->first();
        $katSayur   = DB::table('kategori_bahan_baku')->where('nama', 'Sayuran & Buah')->first();
        $katBumbu   = DB::table('kategori_bahan_baku')->where('nama', 'Bumbu & Rempah')->first();
        $katMinyak  = DB::table('kategori_bahan_baku')->where('nama', 'Minyak & Lemak')->first();
        $katKemasan = DB::table('kategori_bahan_baku')->where('nama', 'Kemasan')->first();

        $prefix = 'BB' . date('Ymd');
        $now = now();

        $items = [
            // ── Minuman ──────────────────────────────────────────
            [
                'kode'                      => $prefix . '001',
                'nama'                      => 'Kopi Arabica',
                'kategori_id'               => $katKopi?->id,
                'satuan_id'                 => $g?->id,          // satuan stok: gram
                'satuan_pembelian_id'       => $kg?->id,         // dibeli per kg
                'jumlah_per_unit_pembelian' => 1000,             // 1 kg = 1000 g
                'harga_beli'                => 150000,           // Rp 150.000/kg → Rp 150/g
                'minimum_stock'             => 500,
                'current_stock'             => 3000,
                'deskripsi'                 => 'Biji kopi arabica premium, sudah digiling halus. Rp 150.000/kg = Rp 150/g',
            ],
            [
                'kode'                      => $prefix . '002',
                'nama'                      => 'Susu Full Cream',
                'kategori_id'               => $katSusu?->id,
                'satuan_id'                 => $ml?->id,
                'satuan_pembelian_id'       => $L?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 18000,            // Rp 18.000/L → Rp 18/ml
                'minimum_stock'             => 2000,
                'current_stock'             => 10000,
                'deskripsi'                 => 'Susu full cream UHT. Rp 18.000/liter = Rp 18/ml',
            ],
            [
                'kode'                      => $prefix . '003',
                'nama'                      => 'Sirup Gula',
                'kategori_id'               => $katSirup?->id,
                'satuan_id'                 => $ml?->id,
                'satuan_pembelian_id'       => $L?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 15000,            // Rp 15.000/L → Rp 15/ml
                'minimum_stock'             => 500,
                'current_stock'             => 3000,
                'deskripsi'                 => 'Simple syrup 1:1. Rp 15.000/liter = Rp 15/ml',
            ],
            [
                'kode'                      => $prefix . '004',
                'nama'                      => 'Teh Celup',
                'kategori_id'               => $katKopi?->id,
                'satuan_id'                 => $pcs?->id,
                'satuan_pembelian_id'       => $pack?->id,
                'jumlah_per_unit_pembelian' => 25,               // 1 pack = 25 pcs
                'harga_beli'                => 12500,            // Rp 12.500/pack → Rp 500/pcs
                'minimum_stock'             => 50,
                'current_stock'             => 100,
                'deskripsi'                 => 'Teh celup premium. Rp 12.500/pack (25pcs) = Rp 500/pcs',
            ],
            [
                'kode'                      => $prefix . '005',
                'nama'                      => 'Coklat Bubuk',
                'kategori_id'               => $katSirup?->id,
                'satuan_id'                 => $g?->id,
                'satuan_pembelian_id'       => $kg?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 80000,            // Rp 80.000/kg → Rp 80/g
                'minimum_stock'             => 200,
                'current_stock'             => 1000,
                'deskripsi'                 => 'Coklat bubuk premium untuk minuman. Rp 80.000/kg = Rp 80/g',
            ],
            // ── Makanan ──────────────────────────────────────────
            [
                'kode'                      => $prefix . '006',
                'nama'                      => 'Dada Ayam Fillet',
                'kategori_id'               => $katDaging?->id,
                'satuan_id'                 => $g?->id,
                'satuan_pembelian_id'       => $kg?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 40000,            // Rp 40.000/kg → Rp 40/g
                'minimum_stock'             => 500,
                'current_stock'             => 3000,
                'deskripsi'                 => 'Fillet dada ayam segar tanpa tulang. Rp 40.000/kg = Rp 40/g',
            ],
            [
                'kode'                      => $prefix . '007',
                'nama'                      => 'Nasi Putih',
                'kategori_id'               => $katTepung?->id,
                'satuan_id'                 => $g?->id,
                'satuan_pembelian_id'       => $kg?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 14000,            // Rp 14.000/kg → Rp 14/g
                'minimum_stock'             => 2000,
                'current_stock'             => 10000,
                'deskripsi'                 => 'Beras premium dimasak menjadi nasi. Rp 14.000/kg = Rp 14/g',
            ],
            [
                'kode'                      => $prefix . '008',
                'nama'                      => 'Telur Ayam',
                'kategori_id'               => $katDaging?->id,
                'satuan_id'                 => $pcs?->id,
                'satuan_pembelian_id'       => $pcs?->id,
                'jumlah_per_unit_pembelian' => 1,
                'harga_beli'                => 2500,             // Rp 2.500/butir
                'minimum_stock'             => 10,
                'current_stock'             => 60,
                'deskripsi'                 => 'Telur ayam segar. Rp 2.500/butir',
            ],
            [
                'kode'                      => $prefix . '009',
                'nama'                      => 'Minyak Goreng',
                'kategori_id'               => $katMinyak?->id,
                'satuan_id'                 => $ml?->id,
                'satuan_pembelian_id'       => $L?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 18000,            // Rp 18.000/L → Rp 18/ml
                'minimum_stock'             => 1000,
                'current_stock'             => 5000,
                'deskripsi'                 => 'Minyak goreng kelapa sawit. Rp 18.000/liter = Rp 18/ml',
            ],
            [
                'kode'                      => $prefix . '010',
                'nama'                      => 'Bumbu Dapur Mix',
                'kategori_id'               => $katBumbu?->id,
                'satuan_id'                 => $g?->id,
                'satuan_pembelian_id'       => $kg?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 25000,            // Rp 25.000/kg → Rp 25/g
                'minimum_stock'             => 200,
                'current_stock'             => 1000,
                'deskripsi'                 => 'Campuran bumbu garam, merica, bawang. Rp 25.000/kg = Rp 25/g',
            ],
            [
                'kode'                      => $prefix . '011',
                'nama'                      => 'Roti Tawar',
                'kategori_id'               => $katTepung?->id,
                'satuan_id'                 => $pcs?->id,        // per lembar
                'satuan_pembelian_id'       => $pack?->id,       // dibeli per pack (10 lembar)
                'jumlah_per_unit_pembelian' => 10,
                'harga_beli'                => 15000,            // Rp 15.000/pack (10 lembar) → Rp 1.500/lembar
                'minimum_stock'             => 10,
                'current_stock'             => 40,
                'deskripsi'                 => 'Roti tawar sandwich. Rp 15.000/pack (10 lembar) = Rp 1.500/lembar',
            ],
            [
                'kode'                      => $prefix . '012',
                'nama'                      => 'Keju Slice',
                'kategori_id'               => $katSusu?->id,
                'satuan_id'                 => $pcs?->id,        // per slice
                'satuan_pembelian_id'       => $pack?->id,
                'jumlah_per_unit_pembelian' => 10,
                'harga_beli'                => 20000,            // Rp 20.000/pack (10 slice) → Rp 2.000/slice
                'minimum_stock'             => 10,
                'current_stock'             => 40,
                'deskripsi'                 => 'Keju slice cheddar. Rp 20.000/pack (10 slice) = Rp 2.000/slice',
            ],
        ];

        foreach ($items as $item) {
            DB::table('bahan_baku')->insert(array_merge([
                'supplier_id'         => null,
                'lokasi_penyimpanan'  => null,
                'expired_date'        => null,
                'gambar_url'          => null,
                'harga_per_satuan_dasar' => null,
                'is_active'           => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ], $item));
        }
    }

    // ─────────────────────────────────────────────
    //  KATEGORI MENU
    // ─────────────────────────────────────────────

    private function seedKategoriMenu(): void
    {
        $categories = [
            ['nama' => 'Makanan',  'deskripsi' => 'Menu makanan berat dan ringan', 'urutan' => 1],
            ['nama' => 'Minuman',  'deskripsi' => 'Minuman panas dan dingin',       'urutan' => 2],
            ['nama' => 'Snack',    'deskripsi' => 'Cemilan dan jajanan',            'urutan' => 3],
        ];

        foreach ($categories as $cat) {
            DB::table('kategori_menu')->insert(array_merge($cat, [
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    // ─────────────────────────────────────────────
    //  MENU
    // ─────────────────────────────────────────────

    private function seedMenu(): void
    {
        $katMakanan = DB::table('kategori_menu')->where('nama', 'Makanan')->first();
        $katMinuman = DB::table('kategori_menu')->where('nama', 'Minuman')->first();

        // Satuan
        $g   = DB::table('satuan')->where('singkatan', 'g')->first();
        $ml  = DB::table('satuan')->where('singkatan', 'ml')->first();
        $pcs = DB::table('satuan')->where('singkatan', 'pcs')->first();

        // Bahan baku
        $kopi      = DB::table('bahan_baku')->where('nama', 'Kopi Arabica')->first();
        $susu      = DB::table('bahan_baku')->where('nama', 'Susu Full Cream')->first();
        $sirup     = DB::table('bahan_baku')->where('nama', 'Sirup Gula')->first();
        $tehCelup  = DB::table('bahan_baku')->where('nama', 'Teh Celup')->first();
        $coklat    = DB::table('bahan_baku')->where('nama', 'Coklat Bubuk')->first();
        $ayam      = DB::table('bahan_baku')->where('nama', 'Dada Ayam Fillet')->first();
        $nasi      = DB::table('bahan_baku')->where('nama', 'Nasi Putih')->first();
        $telur     = DB::table('bahan_baku')->where('nama', 'Telur Ayam')->first();
        $minyak    = DB::table('bahan_baku')->where('nama', 'Minyak Goreng')->first();
        $bumbu     = DB::table('bahan_baku')->where('nama', 'Bumbu Dapur Mix')->first();
        $roti      = DB::table('bahan_baku')->where('nama', 'Roti Tawar')->first();
        $keju      = DB::table('bahan_baku')->where('nama', 'Keju Slice')->first();

        $prefix = 'MN' . date('Ymd');

        // ── 3 MENU MINUMAN ──────────────────────────────────────

        // 1. Americano
        // HPP: 18g kopi (18×150=2700) + 200ml air panas (gratis/sudah overhead)
        $hppAmericano = (18 * 150);
        $americanoId = DB::table('menu')->insertGetId([
            'kode'             => $prefix . '001',
            'nama'             => 'Americano',
            'kategori_id'      => $katMinuman?->id,
            'deskripsi'        => 'Espresso shot dengan air panas. Kuat, pahit, cocok untuk penggemar kopi hitam.',
            'harga_jual'       => 25000,
            'harga_modal'      => $hppAmericano,
            'apply_fixed_cost' => true,
            'is_available'     => true,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->insertBahanBaku($americanoId, [
            [$kopi?->id,  $g?->id,  18,  'Double shot espresso'],
        ]);

        // 2. Cafe Latte
        // HPP: 18g kopi (2700) + 150ml susu (150×18=2700)
        $hppLatte = (18 * 150) + (150 * 18);
        $latteId = DB::table('menu')->insertGetId([
            'kode'             => $prefix . '002',
            'nama'             => 'Cafe Latte',
            'kategori_id'      => $katMinuman?->id,
            'deskripsi'        => 'Espresso dengan susu steamed yang creamy. Lembut dengan rasa kopi yang seimbang.',
            'harga_jual'       => 32000,
            'harga_modal'      => $hppLatte,
            'apply_fixed_cost' => true,
            'is_available'     => true,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->insertBahanBaku($latteId, [
            [$kopi?->id, $g?->id,  18,  'Espresso shot'],
            [$susu?->id, $ml?->id, 150, 'Susu steamed'],
        ]);

        // 3. Coklat Susu
        // HPP: 25g coklat (25×80=2000) + 200ml susu (200×18=3600) + 20ml sirup (20×15=300)
        $hppChoco = (25 * 80) + (200 * 18) + (20 * 15);
        $chocoId = DB::table('menu')->insertGetId([
            'kode'             => $prefix . '003',
            'nama'             => 'Coklat Susu',
            'kategori_id'      => $katMinuman?->id,
            'deskripsi'        => 'Minuman coklat panas dengan susu creamy dan sirup gula. Hangat dan manis.',
            'harga_jual'       => 28000,
            'harga_modal'      => $hppChoco,
            'apply_fixed_cost' => true,
            'is_available'     => true,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->insertBahanBaku($chocoId, [
            [$coklat?->id, $g?->id,  25, 'Coklat bubuk'],
            [$susu?->id,   $ml?->id, 200, 'Susu steamed'],
            [$sirup?->id,  $ml?->id, 20,  'Sirup gula'],
        ]);

        // ── 3 MENU MAKANAN ──────────────────────────────────────

        // 4. Nasi Ayam Goreng
        // HPP: 250g nasi (250×14=3500) + 120g ayam (120×40=4800) + 30ml minyak (30×18=540) + 5g bumbu (5×25=125)
        $hppNasiAyam = (250 * 14) + (120 * 40) + (30 * 18) + (5 * 25);
        $nasiAyamId = DB::table('menu')->insertGetId([
            'kode'             => $prefix . '004',
            'nama'             => 'Nasi Ayam Goreng',
            'kategori_id'      => $katMakanan?->id,
            'deskripsi'        => 'Nasi putih hangat dengan potongan ayam goreng krispy berbumbu. Porsi mengenyangkan.',
            'harga_jual'       => 35000,
            'harga_modal'      => $hppNasiAyam,
            'apply_fixed_cost' => true,
            'is_available'     => true,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->insertBahanBaku($nasiAyamId, [
            [$nasi?->id,   $g?->id,  250, 'Nasi putih'],
            [$ayam?->id,   $g?->id,  120, 'Fillet ayam goreng'],
            [$minyak?->id, $ml?->id, 30,  'Minyak goreng'],
            [$bumbu?->id,  $g?->id,  5,   'Bumbu ayam'],
        ]);

        // 5. Sandwich Keju Telur
        // HPP: 2 roti (2×1500=3000) + 2 keju (2×2000=4000) + 2 telur (2×2500=5000) + 10ml minyak (180)
        $hppSandwich = (2 * 1500) + (2 * 2000) + (2 * 2500) + (10 * 18);
        $sandwichId = DB::table('menu')->insertGetId([
            'kode'             => $prefix . '005',
            'nama'             => 'Sandwich Keju Telur',
            'kategori_id'      => $katMakanan?->id,
            'deskripsi'        => 'Roti tawar dengan telur mata sapi dan keju cheddar slice. Sarapan simpel dan lezat.',
            'harga_jual'       => 30000,
            'harga_modal'      => $hppSandwich,
            'apply_fixed_cost' => true,
            'is_available'     => true,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->insertBahanBaku($sandwichId, [
            [$roti?->id,   $pcs?->id, 2,  'Roti tawar'],
            [$keju?->id,   $pcs?->id, 2,  'Keju slice cheddar'],
            [$telur?->id,  $pcs?->id, 2,  'Telur mata sapi'],
            [$minyak?->id, $ml?->id,  10, 'Minyak untuk memanggang'],
        ]);

        // 6. Nasi Telur Dadar
        // HPP: 250g nasi (3500) + 2 telur (5000) + 20ml minyak (360) + 5g bumbu (125)
        $hppNasiTelur = (250 * 14) + (2 * 2500) + (20 * 18) + (5 * 25);
        $nasiTelurId = DB::table('menu')->insertGetId([
            'kode'             => $prefix . '006',
            'nama'             => 'Nasi Telur Dadar',
            'kategori_id'      => $katMakanan?->id,
            'deskripsi'        => 'Nasi putih dengan telur dadar tebal berbumbu. Menu sederhana, harga terjangkau.',
            'harga_jual'       => 22000,
            'harga_modal'      => $hppNasiTelur,
            'apply_fixed_cost' => true,
            'is_available'     => true,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->insertBahanBaku($nasiTelurId, [
            [$nasi?->id,   $g?->id,  250, 'Nasi putih'],
            [$telur?->id,  $pcs?->id, 2,  'Telur dadar'],
            [$minyak?->id, $ml?->id, 20,  'Minyak goreng'],
            [$bumbu?->id,  $g?->id,  5,   'Bumbu dadar'],
        ]);
    }

    /**
     * Helper: insert rows ke menu_bahan_baku
     * @param int $menuId
     * @param array $items  [ [bahan_baku_id, satuan_id, jumlah, keterangan], ... ]
     */
    private function insertBahanBaku(int $menuId, array $items): void
    {
        foreach ($items as [$bahanId, $satuanId, $jumlah, $ket]) {
            if (!$bahanId || !$satuanId) continue;
            DB::table('menu_bahan_baku')->insert([
                'menu_id'      => $menuId,
                'bahan_baku_id'=> $bahanId,
                'satuan_id'    => $satuanId,
                'jumlah'       => $jumlah,
                'keterangan'   => $ket,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }
}
