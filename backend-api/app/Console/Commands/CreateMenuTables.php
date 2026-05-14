<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class CreateMenuTables extends Command
{
    protected $signature = 'outlets:create-menu-tables {--outlet= : Specific outlet ID}';
    protected $description = 'Create menu related tables in outlet schemas';

    public function handle()
    {
        $this->info('Creating menu tables in outlet schemas...');

        $outlets = $this->option('outlet')
            ? Outlet::where('id', $this->option('outlet'))->get()
            : Outlet::all();

        if ($outlets->isEmpty()) {
            $this->warn('No outlets found.');
            return 0;
        }

        foreach ($outlets as $outlet) {
            $this->info("Processing outlet: {$outlet->name} ({$outlet->schema_name})");
            try {
                $this->createTables($outlet->schema_name);
                $this->info("✅ Menu tables created for {$outlet->name}");
            } catch (\Exception $e) {
                $this->error("❌ Failed for {$outlet->name}: " . $e->getMessage());
            }
        }

        return 0;
    }

    private function createTables($schema)
    {
        // 1. Kategori Menu
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.kategori_menu (
                id SERIAL PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                deskripsi TEXT,
                urutan INTEGER DEFAULT 0,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");

        // 2. Menu
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.menu (
                id SERIAL PRIMARY KEY,
                kode VARCHAR(50) UNIQUE NOT NULL,
                nama VARCHAR(200) NOT NULL,
                kategori_id INTEGER REFERENCES {$schema}.kategori_menu(id),
                deskripsi TEXT,
                harga_jual DECIMAL(15,2) NOT NULL DEFAULT 0,
                harga_modal DECIMAL(15,2) DEFAULT 0,
                apply_fixed_cost BOOLEAN DEFAULT true,
                gambar_url VARCHAR(255),
                is_available BOOLEAN DEFAULT true,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");

        // 3. Menu Bahan Baku (resep - bahan baku yang digunakan per menu)
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.menu_bahan_baku (
                id SERIAL PRIMARY KEY,
                menu_id INTEGER REFERENCES {$schema}.menu(id) ON DELETE CASCADE,
                bahan_baku_id INTEGER REFERENCES {$schema}.bahan_baku(id),
                satuan_id INTEGER REFERENCES {$schema}.satuan(id),
                jumlah DECIMAL(10,4) NOT NULL,
                keterangan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Indexes
        DB::statement("CREATE INDEX IF NOT EXISTS idx_menu_kategori ON {$schema}.menu(kategori_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_menu_bahan_menu ON {$schema}.menu_bahan_baku(menu_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_menu_bahan_baku ON {$schema}.menu_bahan_baku(bahan_baku_id)");
    }
}
