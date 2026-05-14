<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

class CreatePromoTables extends Command
{
    protected $signature = 'outlets:create-promo-tables';
    protected $description = 'Create promo tables in all outlet schemas';

    public function handle()
    {
        $outlets = Outlet::all();
        
        if ($outlets->isEmpty()) {
            $this->error('No outlets found. Please create outlets first.');
            return 1;
        }

        foreach ($outlets as $outlet) {
            $this->info("Creating promo tables for outlet: {$outlet->name} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Create promos table
                if (!$this->tableExists('promos')) {
                    DB::statement("
                        CREATE TABLE promos (
                            id SERIAL PRIMARY KEY,
                            kode VARCHAR(50) UNIQUE NOT NULL,
                            nama VARCHAR(100) NOT NULL,
                            deskripsi TEXT,
                            tipe VARCHAR(20) NOT NULL CHECK (tipe IN ('percentage', 'nominal')),
                            nilai DECIMAL(10,2) NOT NULL,
                            minimum_pembelian DECIMAL(10,2) DEFAULT 0,
                            maksimum_diskon DECIMAL(10,2),
                            tanggal_mulai DATE NOT NULL,
                            tanggal_selesai DATE NOT NULL,
                            jam_mulai TIME,
                            jam_selesai TIME,
                            hari_aktif VARCHAR(50),
                            kuota_penggunaan INTEGER,
                            jumlah_terpakai INTEGER DEFAULT 0,
                            is_active BOOLEAN DEFAULT true,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            deleted_at TIMESTAMP
                        )
                    ");
                    $this->info('  ✓ Created promos table');
                } else {
                    $this->warn('  - promos table already exists');
                }

                // Create indexes
                DB::statement("CREATE INDEX IF NOT EXISTS idx_promos_kode ON promos(kode)");
                DB::statement("CREATE INDEX IF NOT EXISTS idx_promos_active ON promos(is_active)");
                DB::statement("CREATE INDEX IF NOT EXISTS idx_promos_dates ON promos(tanggal_mulai, tanggal_selesai)");
                
                $this->info('  ✓ Created indexes');

                // Seed sample promos
                $this->seedPromos($outlet);
                
                DB::statement("SET search_path TO public");
                
                $this->info("✓ Successfully created promo tables for {$outlet->name}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->error("Failed to create tables for {$outlet->name}: " . $e->getMessage());
                return 1;
            }
        }

        $this->info('All promo tables created successfully!');
        return 0;
    }

    private function tableExists($tableName)
    {
        $result = DB::select("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = current_schema()
                AND table_name = ?
            )
        ", [$tableName]);
        
        return $result[0]->exists;
    }

    private function seedPromos($outlet)
    {
        $count = DB::table('promos')->count();
        
        if ($count > 0) {
            $this->warn('  - Sample promos already exist');
            return;
        }

        $promos = [
            [
                'kode' => 'HAPPY-HOUR',
                'nama' => 'Happy Hour',
                'deskripsi' => 'Diskon 20% untuk semua menu pada jam 14:00-16:00',
                'tipe' => 'percentage',
                'nilai' => 20,
                'minimum_pembelian' => 0,
                'maksimum_diskon' => 50000,
                'tanggal_mulai' => date('Y-m-d'),
                'tanggal_selesai' => date('Y-m-d', strtotime('+1 year')),
                'jam_mulai' => '14:00:00',
                'jam_selesai' => '16:00:00',
                'hari_aktif' => 'senin,selasa,rabu,kamis,jumat',
                'kuota_penggunaan' => null,
                'is_active' => true,
            ],
            [
                'kode' => 'WEEKEND-50K',
                'nama' => 'Weekend Special',
                'deskripsi' => 'Diskon Rp 50.000 untuk pembelian min Rp 200.000 di weekend',
                'tipe' => 'nominal',
                'nilai' => 50000,
                'minimum_pembelian' => 200000,
                'maksimum_diskon' => null,
                'tanggal_mulai' => date('Y-m-d'),
                'tanggal_selesai' => date('Y-m-d', strtotime('+6 months')),
                'jam_mulai' => null,
                'jam_selesai' => null,
                'hari_aktif' => 'sabtu,minggu',
                'kuota_penggunaan' => 100,
                'is_active' => true,
            ],
            [
                'kode' => 'BREAKFAST-15',
                'nama' => 'Breakfast Promo',
                'deskripsi' => 'Diskon 15% untuk sarapan pagi (07:00-10:00)',
                'tipe' => 'percentage',
                'nilai' => 15,
                'minimum_pembelian' => 50000,
                'maksimum_diskon' => 30000,
                'tanggal_mulai' => date('Y-m-d'),
                'tanggal_selesai' => date('Y-m-d', strtotime('+3 months')),
                'jam_mulai' => '07:00:00',
                'jam_selesai' => '10:00:00',
                'hari_aktif' => 'senin,selasa,rabu,kamis,jumat,sabtu,minggu',
                'kuota_penggunaan' => null,
                'is_active' => true,
            ],
        ];

        foreach ($promos as $promo) {
            DB::table('promos')->insert($promo);
        }

        $this->info('  ✓ Seeded ' . count($promos) . ' sample promos');
    }
}
