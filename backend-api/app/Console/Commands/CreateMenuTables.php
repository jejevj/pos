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
        // Create the table without FKs to bahan_baku/satuan so this command
        // can be run before outlets:create-bahan-baku-tables. The FKs are
        // attached afterwards (idempotently) when the referenced tables exist.
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.menu_bahan_baku (
                id SERIAL PRIMARY KEY,
                menu_id INTEGER REFERENCES {$schema}.menu(id) ON DELETE CASCADE,
                bahan_baku_id INTEGER,
                satuan_id INTEGER,
                jumlah DECIMAL(10,4) NOT NULL,
                keterangan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Attach FKs only if prerequisite tables already exist. Safe to rerun.
        $this->ensureForeignKey($schema, 'menu_bahan_baku', 'fk_menu_bahan_baku_bahan', 'bahan_baku_id', 'bahan_baku', 'id');
        $this->ensureForeignKey($schema, 'menu_bahan_baku', 'fk_menu_bahan_baku_satuan', 'satuan_id', 'satuan', 'id');

        // Indexes
        DB::statement("CREATE INDEX IF NOT EXISTS idx_menu_kategori ON {$schema}.menu(kategori_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_menu_bahan_menu ON {$schema}.menu_bahan_baku(menu_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_menu_bahan_baku ON {$schema}.menu_bahan_baku(bahan_baku_id)");
    }

    private function tableExists($schema, $table)
    {
        $row = DB::selectOne(
            "SELECT EXISTS (
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = ? AND table_name = ?
            ) AS exists",
            [$schema, $table]
        );
        return (bool) ($row->exists ?? false);
    }

    private function constraintExists($schema, $table, $constraint)
    {
        $row = DB::selectOne(
            "SELECT EXISTS (
                SELECT 1 FROM information_schema.table_constraints
                WHERE table_schema = ? AND table_name = ? AND constraint_name = ?
            ) AS exists",
            [$schema, $table, $constraint]
        );
        return (bool) ($row->exists ?? false);
    }

    private function ensureForeignKey($schema, $table, $constraint, $column, $refTable, $refColumn)
    {
        if (! $this->tableExists($schema, $refTable)) {
            $this->warn("  - Skipping FK {$constraint}: {$schema}.{$refTable} does not exist yet. Run outlets:create-bahan-baku-tables, then re-run this command.");
            return;
        }
        if ($this->constraintExists($schema, $table, $constraint)) {
            return;
        }
        DB::statement(
            "ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$constraint} " .
            "FOREIGN KEY ({$column}) REFERENCES {$schema}.{$refTable}({$refColumn})"
        );
    }
}
