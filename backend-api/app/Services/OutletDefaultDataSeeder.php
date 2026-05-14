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
 * Idempotent per-item: setiap row di-cek by nama sebelum insert,
 * sehingga aman dipanggil berulang kali tanpa duplikasi.
 * Jika sebagian data sudah ada (misal satuan sudah ada tapi menu belum),
 * hanya bagian yang belum ada yang akan di-insert.
 */
class OutletDefaultDataSeeder
{
    private string $schema;

    /** Statistik untuk command output */
    public array $stats = [
        'satuan'           => ['inserted' => 0, 'skipped' => 0],
        'kategori_bb'      => ['inserted' => 0, 'skipped' => 0],
        'bahan_baku'       => ['inserted' => 0, 'skipped' => 0],
        'kategori_menu'    => ['inserted' => 0, 'skipped' => 0],
        'menu'             => ['inserted' => 0, 'skipped' => 0],
    ];

    public function seed(string $schema): void
    {
        $this->schema = $schema;
        $this->stats  = [
            'satuan'        => ['inserted' => 0, 'skipped' => 0],
            'kategori_bb'   => ['inserted' => 0, 'skipped' => 0],
            'bahan_baku'    => ['inserted' => 0, 'skipped' => 0],
            'kategori_menu' => ['inserted' => 0, 'skipped' => 0],
            'menu'          => ['inserted' => 0, 'skipped' => 0],
        ];

        try {
            DB::statement("SET search_path TO {$schema}, public");

            $this->seedSatuan();
            $this->seedKategoriBahanBaku();
            $this->seedBahanBaku();
            $this->seedKategoriMenu();
            $this->seedMenu();

            Log::info("OutletDefaultDataSeeder: schema {$schema} seeded.", $this->stats);
        } catch (\Throwable $e) {
            Log::error("OutletDefaultDataSeeder failed for schema {$schema}: " . $e->getMessage());
            throw $e; // lempar ke caller agar bisa di-catch command
        } finally {
            DB::statement('SET search_path TO public');
        }
    }

    // ─────────────────────────────────────────────
    //  SATUAN
    // ─────────────────────────────────────────────

    private function seedSatuan(): void
    {
        $now   = now();
        $units = [
            // Base units
            ['nama' => 'Gram',       'singkatan' => 'g',    'tipe' => 'weight', 'is_base_unit' => true,  'conversion_to_base' => null, 'deskripsi' => 'Satuan dasar berat'],
            ['nama' => 'Mililiter',  'singkatan' => 'ml',   'tipe' => 'volume', 'is_base_unit' => true,  'conversion_to_base' => null, 'deskripsi' => 'Satuan dasar volume'],
            ['nama' => 'Pieces',     'singkatan' => 'pcs',  'tipe' => 'count',  'is_base_unit' => true,  'conversion_to_base' => null, 'deskripsi' => 'Satuan hitung satuan'],
            // Derived units
            ['nama' => 'Kilogram',   'singkatan' => 'kg',   'tipe' => 'weight', 'is_base_unit' => false, 'conversion_to_base' => 1000, 'deskripsi' => '1 kg = 1000 gram'],
            ['nama' => 'Ons',        'singkatan' => 'ons',  'tipe' => 'weight', 'is_base_unit' => false, 'conversion_to_base' => 100,  'deskripsi' => '1 ons = 100 gram'],
            ['nama' => 'Liter',      'singkatan' => 'L',    'tipe' => 'volume', 'is_base_unit' => false, 'conversion_to_base' => 1000, 'deskripsi' => '1 L = 1000 ml'],
            ['nama' => 'Centiliter', 'singkatan' => 'cl',   'tipe' => 'volume', 'is_base_unit' => false, 'conversion_to_base' => 10,   'deskripsi' => '1 cl = 10 ml'],
            ['nama' => 'Lusin',      'singkatan' => 'lusin','tipe' => 'count',  'is_base_unit' => false, 'conversion_to_base' => 12,   'deskripsi' => '1 lusin = 12 pcs'],
            ['nama' => 'Pack',       'singkatan' => 'pack', 'tipe' => 'count',  'is_base_unit' => false, 'conversion_to_base' => null, 'deskripsi' => 'Kemasan pack'],
            ['nama' => 'Box',        'singkatan' => 'box',  'tipe' => 'count',  'is_base_unit' => false, 'conversion_to_base' => null, 'deskripsi' => 'Kemasan box'],
        ];

        foreach ($units as $unit) {
            $exists = DB::table('satuan')->where('nama', $unit['nama'])->exists();
            if ($exists) {
                $this->stats['satuan']['skipped']++;
                continue;
            }
            DB::table('satuan')->insert(array_merge($unit, [
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
            $this->stats['satuan']['inserted']++;
        }
    }

    // ─────────────────────────────────────────────
    //  KATEGORI BAHAN BAKU
    // ─────────────────────────────────────────────

    private function seedKategoriBahanBaku(): void
    {
        $now        = now();
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
            $exists = DB::table('kategori_bahan_baku')->where('nama', $cat['nama'])->exists();
            if ($exists) {
                $this->stats['kategori_bb']['skipped']++;
                continue;
            }
            DB::table('kategori_bahan_baku')->insert(array_merge($cat, [
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
            $this->stats['kategori_bb']['inserted']++;
        }
    }

    // ─────────────────────────────────────────────
    //  BAHAN BAKU
    // ─────────────────────────────────────────────

    private function seedBahanBaku(): void
    {
        // Ambil satuan by singkatan
        $g    = DB::table('satuan')->where('singkatan', 'g')->first();
        $ml   = DB::table('satuan')->where('singkatan', 'ml')->first();
        $kg   = DB::table('satuan')->where('singkatan', 'kg')->first();
        $L    = DB::table('satuan')->where('singkatan', 'L')->first();
        $pcs  = DB::table('satuan')->where('singkatan', 'pcs')->first();
        $pack = DB::table('satuan')->where('singkatan', 'pack')->first();

        // Ambil kategori by nama
        $katKopi   = DB::table('kategori_bahan_baku')->where('nama', 'Biji Kopi & Teh')->first();
        $katSusu   = DB::table('kategori_bahan_baku')->where('nama', 'Susu & Dairy')->first();
        $katSirup  = DB::table('kategori_bahan_baku')->where('nama', 'Sirup & Perasa')->first();
        $katTepung = DB::table('kategori_bahan_baku')->where('nama', 'Tepung & Biji-bijian')->first();
        $katDaging = DB::table('kategori_bahan_baku')->where('nama', 'Daging & Protein')->first();
        $katBumbu  = DB::table('kategori_bahan_baku')->where('nama', 'Bumbu & Rempah')->first();
        $katMinyak = DB::table('kategori_bahan_baku')->where('nama', 'Minyak & Lemak')->first();
        $katSusu2  = DB::table('kategori_bahan_baku')->where('nama', 'Susu & Dairy')->first();

        $prefix = 'BB' . date('Ymd');
        $now    = now();

        $items = [
            [
                'nama'                      => 'Kopi Arabica',
                'kode'                      => $prefix . '001',
                'kategori_id'               => $katKopi?->id,
                'satuan_id'                 => $g?->id,
                'satuan_pembelian_id'       => $kg?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 150000,
                'minimum_stock'             => 500,
                'current_stock'             => 3000,
                'deskripsi'                 => 'Biji kopi arabica premium, sudah digiling halus. Rp 150.000/kg = Rp 150/g',
            ],
            [
                'nama'                      => 'Susu Full Cream',
                'kode'                      => $prefix . '002',
                'kategori_id'               => $katSusu?->id,
                'satuan_id'                 => $ml?->id,
                'satuan_pembelian_id'       => $L?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 18000,
                'minimum_stock'             => 2000,
                'current_stock'             => 10000,
                'deskripsi'                 => 'Susu full cream UHT. Rp 18.000/liter = Rp 18/ml',
            ],
            [
                'nama'                      => 'Sirup Gula',
                'kode'                      => $prefix . '003',
                'kategori_id'               => $katSirup?->id,
                'satuan_id'                 => $ml?->id,
                'satuan_pembelian_id'       => $L?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 15000,
                'minimum_stock'             => 500,
                'current_stock'             => 3000,
                'deskripsi'                 => 'Simple syrup 1:1. Rp 15.000/liter = Rp 15/ml',
            ],
            [
                'nama'                      => 'Teh Celup',
                'kode'                      => $prefix . '004',
                'kategori_id'               => $katKopi?->id,
                'satuan_id'                 => $pcs?->id,
                'satuan_pembelian_id'       => $pack?->id,
                'jumlah_per_unit_pembelian' => 25,
                'harga_beli'                => 12500,
                'minimum_stock'             => 50,
                'current_stock'             => 100,
                'deskripsi'                 => 'Teh celup premium. Rp 12.500/pack (25pcs) = Rp 500/pcs',
            ],
            [
                'nama'                      => 'Coklat Bubuk',
                'kode'                      => $prefix . '005',
                'kategori_id'               => $katSirup?->id,
                'satuan_id'                 => $g?->id,
                'satuan_pembelian_id'       => $kg?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 80000,
                'minimum_stock'             => 200,
                'current_stock'             => 1000,
                'deskripsi'                 => 'Coklat bubuk premium untuk minuman. Rp 80.000/kg = Rp 80/g',
            ],
            [
                'nama'                      => 'Dada Ayam Fillet',
                'kode'                      => $prefix . '006',
                'kategori_id'               => $katDaging?->id,
                'satuan_id'                 => $g?->id,
                'satuan_pembelian_id'       => $kg?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 40000,
                'minimum_stock'             => 500,
                'current_stock'             => 3000,
                'deskripsi'                 => 'Fillet dada ayam segar tanpa tulang. Rp 40.000/kg = Rp 40/g',
            ],
            [
                'nama'                      => 'Nasi Putih',
                'kode'                      => $prefix . '007',
                'kategori_id'               => $katTepung?->id,
                'satuan_id'                 => $g?->id,
                'satuan_pembelian_id'       => $kg?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 14000,
                'minimum_stock'             => 2000,
                'current_stock'             => 10000,
                'deskripsi'                 => 'Beras premium dimasak menjadi nasi. Rp 14.000/kg = Rp 14/g',
            ],
            [
                'nama'                      => 'Telur Ayam',
                'kode'                      => $prefix . '008',
                'kategori_id'               => $katDaging?->id,
                'satuan_id'                 => $pcs?->id,
                'satuan_pembelian_id'       => $pcs?->id,
                'jumlah_per_unit_pembelian' => 1,
                'harga_beli'                => 2500,
                'minimum_stock'             => 10,
                'current_stock'             => 60,
                'deskripsi'                 => 'Telur ayam segar. Rp 2.500/butir',
            ],
            [
                'nama'                      => 'Minyak Goreng',
                'kode'                      => $prefix . '009',
                'kategori_id'               => $katMinyak?->id,
                'satuan_id'                 => $ml?->id,
                'satuan_pembelian_id'       => $L?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 18000,
                'minimum_stock'             => 1000,
                'current_stock'             => 5000,
                'deskripsi'                 => 'Minyak goreng kelapa sawit. Rp 18.000/liter = Rp 18/ml',
            ],
            [
                'nama'                      => 'Bumbu Dapur Mix',
                'kode'                      => $prefix . '010',
                'kategori_id'               => $katBumbu?->id,
                'satuan_id'                 => $g?->id,
                'satuan_pembelian_id'       => $kg?->id,
                'jumlah_per_unit_pembelian' => 1000,
                'harga_beli'                => 25000,
                'minimum_stock'             => 200,
                'current_stock'             => 1000,
                'deskripsi'                 => 'Campuran bumbu garam, merica, bawang. Rp 25.000/kg = Rp 25/g',
            ],
            [
                'nama'                      => 'Roti Tawar',
                'kode'                      => $prefix . '011',
                'kategori_id'               => $katTepung?->id,
                'satuan_id'                 => $pcs?->id,
                'satuan_pembelian_id'       => $pack?->id,
                'jumlah_per_unit_pembelian' => 10,
                'harga_beli'                => 15000,
                'minimum_stock'             => 10,
                'current_stock'             => 40,
                'deskripsi'                 => 'Roti tawar sandwich. Rp 15.000/pack (10 lembar) = Rp 1.500/lembar',
            ],
            [
                'nama'                      => 'Keju Slice',
                'kode'                      => $prefix . '012',
                'kategori_id'               => $katSusu2?->id,
                'satuan_id'                 => $pcs?->id,
                'satuan_pembelian_id'       => $pack?->id,
                'jumlah_per_unit_pembelian' => 10,
                'harga_beli'                => 20000,
                'minimum_stock'             => 10,
                'current_stock'             => 40,
                'deskripsi'                 => 'Keju slice cheddar. Rp 20.000/pack (10 slice) = Rp 2.000/slice',
            ],
        ];

        foreach ($items as $item) {
            $exists = DB::table('bahan_baku')->where('nama', $item['nama'])->exists();
            if ($exists) {
                $this->stats['bahan_baku']['skipped']++;
                continue;
            }
            DB::table('bahan_baku')->insert(array_merge([
                'supplier_id'        => null,
                'lokasi_penyimpanan' => null,
                'expired_date'       => null,
                'gambar_url'         => null,
                'defers_on_bon'      => false,
                'is_active'          => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ], $item));
            $this->stats['bahan_baku']['inserted']++;
        }
    }

    // ─────────────────────────────────────────────
    //  KATEGORI MENU
    // ─────────────────────────────────────────────

    private function seedKategoriMenu(): void
    {
        $now        = now();
        $categories = [
            ['nama' => 'Makanan', 'deskripsi' => 'Menu makanan berat dan ringan', 'urutan' => 1],
            ['nama' => 'Minuman', 'deskripsi' => 'Minuman panas dan dingin',      'urutan' => 2],
            ['nama' => 'Snack',   'deskripsi' => 'Cemilan dan jajanan',           'urutan' => 3],
        ];

        foreach ($categories as $cat) {
            $exists = DB::table('kategori_menu')->where('nama', $cat['nama'])->exists();
            if ($exists) {
                $this->stats['kategori_menu']['skipped']++;
                continue;
            }
            DB::table('kategori_menu')->insert(array_merge($cat, [
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
            $this->stats['kategori_menu']['inserted']++;
        }
    }

    // ─────────────────────────────────────────────
    //  MENU
    // ─────────────────────────────────────────────

    private function seedMenu(): void
    {
        $katMakanan = DB::table('kategori_menu')->where('nama', 'Makanan')->first();
        $katMinuman = DB::table('kategori_menu')->where('nama', 'Minuman')->first();

        $g   = DB::table('satuan')->where('singkatan', 'g')->first();
        $ml  = DB::table('satuan')->where('singkatan', 'ml')->first();
        $pcs = DB::table('satuan')->where('singkatan', 'pcs')->first();

        $kopi   = DB::table('bahan_baku')->where('nama', 'Kopi Arabica')->first();
        $susu   = DB::table('bahan_baku')->where('nama', 'Susu Full Cream')->first();
        $sirup  = DB::table('bahan_baku')->where('nama', 'Sirup Gula')->first();
        $coklat = DB::table('bahan_baku')->where('nama', 'Coklat Bubuk')->first();
        $ayam   = DB::table('bahan_baku')->where('nama', 'Dada Ayam Fillet')->first();
        $nasi   = DB::table('bahan_baku')->where('nama', 'Nasi Putih')->first();
        $telur  = DB::table('bahan_baku')->where('nama', 'Telur Ayam')->first();
        $minyak = DB::table('bahan_baku')->where('nama', 'Minyak Goreng')->first();
        $bumbu  = DB::table('bahan_baku')->where('nama', 'Bumbu Dapur Mix')->first();
        $roti   = DB::table('bahan_baku')->where('nama', 'Roti Tawar')->first();
        $keju   = DB::table('bahan_baku')->where('nama', 'Keju Slice')->first();

        $prefix = 'MN' . date('Ymd');
        $now    = now();

        $menuItems = [
            // ── 3 MINUMAN ────────────────────────────────────────
            [
                'nama'        => 'Americano',
                'kode'        => $prefix . '001',
                'kategori_id' => $katMinuman?->id,
                'deskripsi'   => 'Espresso shot dengan air panas. Kuat, pahit, cocok untuk penggemar kopi hitam.',
                'harga_jual'  => 25000,
                'harga_modal' => (18 * 150),       // 18g kopi × Rp150/g
                'bahan_baku'  => [
                    [$kopi?->id, $g?->id, 18, 'Double shot espresso'],
                ],
            ],
            [
                'nama'        => 'Cafe Latte',
                'kode'        => $prefix . '002',
                'kategori_id' => $katMinuman?->id,
                'deskripsi'   => 'Espresso dengan susu steamed yang creamy. Lembut dengan rasa kopi yang seimbang.',
                'harga_jual'  => 32000,
                'harga_modal' => (18 * 150) + (150 * 18),
                'bahan_baku'  => [
                    [$kopi?->id, $g?->id,  18,  'Espresso shot'],
                    [$susu?->id, $ml?->id, 150, 'Susu steamed'],
                ],
            ],
            [
                'nama'        => 'Coklat Susu',
                'kode'        => $prefix . '003',
                'kategori_id' => $katMinuman?->id,
                'deskripsi'   => 'Minuman coklat panas dengan susu creamy dan sirup gula. Hangat dan manis.',
                'harga_jual'  => 28000,
                'harga_modal' => (25 * 80) + (200 * 18) + (20 * 15),
                'bahan_baku'  => [
                    [$coklat?->id, $g?->id,  25,  'Coklat bubuk'],
                    [$susu?->id,   $ml?->id, 200, 'Susu steamed'],
                    [$sirup?->id,  $ml?->id, 20,  'Sirup gula'],
                ],
            ],
            // ── 3 MAKANAN ────────────────────────────────────────
            [
                'nama'        => 'Nasi Ayam Goreng',
                'kode'        => $prefix . '004',
                'kategori_id' => $katMakanan?->id,
                'deskripsi'   => 'Nasi putih hangat dengan potongan ayam goreng krispy berbumbu. Porsi mengenyangkan.',
                'harga_jual'  => 35000,
                'harga_modal' => (250 * 14) + (120 * 40) + (30 * 18) + (5 * 25),
                'bahan_baku'  => [
                    [$nasi?->id,   $g?->id,  250, 'Nasi putih'],
                    [$ayam?->id,   $g?->id,  120, 'Fillet ayam goreng'],
                    [$minyak?->id, $ml?->id, 30,  'Minyak goreng'],
                    [$bumbu?->id,  $g?->id,  5,   'Bumbu ayam'],
                ],
            ],
            [
                'nama'        => 'Sandwich Keju Telur',
                'kode'        => $prefix . '005',
                'kategori_id' => $katMakanan?->id,
                'deskripsi'   => 'Roti tawar dengan telur mata sapi dan keju cheddar slice. Sarapan simpel dan lezat.',
                'harga_jual'  => 30000,
                'harga_modal' => (2 * 1500) + (2 * 2000) + (2 * 2500) + (10 * 18),
                'bahan_baku'  => [
                    [$roti?->id,   $pcs?->id, 2,  'Roti tawar'],
                    [$keju?->id,   $pcs?->id, 2,  'Keju slice cheddar'],
                    [$telur?->id,  $pcs?->id, 2,  'Telur mata sapi'],
                    [$minyak?->id, $ml?->id,  10, 'Minyak untuk memanggang'],
                ],
            ],
            [
                'nama'        => 'Nasi Telur Dadar',
                'kode'        => $prefix . '006',
                'kategori_id' => $katMakanan?->id,
                'deskripsi'   => 'Nasi putih dengan telur dadar tebal berbumbu. Menu sederhana, harga terjangkau.',
                'harga_jual'  => 22000,
                'harga_modal' => (250 * 14) + (2 * 2500) + (20 * 18) + (5 * 25),
                'bahan_baku'  => [
                    [$nasi?->id,   $g?->id,  250, 'Nasi putih'],
                    [$telur?->id,  $pcs?->id, 2,  'Telur dadar'],
                    [$minyak?->id, $ml?->id, 20,  'Minyak goreng'],
                    [$bumbu?->id,  $g?->id,  5,   'Bumbu dadar'],
                ],
            ],
        ];

        foreach ($menuItems as $item) {
            $exists = DB::table('menu')->where('nama', $item['nama'])->exists();
            if ($exists) {
                $this->stats['menu']['skipped']++;
                continue;
            }

            $bahanBaku = $item['bahan_baku'];
            unset($item['bahan_baku']);

            $menuId = DB::table('menu')->insertGetId(array_merge([
                'station_id'       => null,
                'gambar_url'       => null,
                'apply_fixed_cost' => true,
                'is_available'     => true,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ], $item));

            $this->insertMenuBahanBaku($menuId, $bahanBaku);
            $this->stats['menu']['inserted']++;
        }
    }

    /**
     * Helper: insert rows ke menu_bahan_baku
     * @param int   $menuId
     * @param array $items  [ [bahan_baku_id, satuan_id, jumlah, keterangan], ... ]
     */
    private function insertMenuBahanBaku(int $menuId, array $items): void
    {
        $now = now();
        foreach ($items as [$bahanId, $satuanId, $jumlah, $ket]) {
            if (!$bahanId || !$satuanId) continue;
            DB::table('menu_bahan_baku')->insert([
                'menu_id'       => $menuId,
                'bahan_baku_id' => $bahanId,
                'satuan_id'     => $satuanId,
                'jumlah'        => $jumlah,
                'keterangan'    => $ket,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }
}
