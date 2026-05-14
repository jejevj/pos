<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class CreateStationTables extends Command
{
    protected $signature = 'outlets:create-station-tables {--outlet= : Specific outlet ID}';
    protected $description = 'Create station tables and update order_items with status column';

    public function handle()
    {
        $outlets = $this->option('outlet')
            ? Outlet::where('id', $this->option('outlet'))->get()
            : Outlet::all();

        if ($outlets->isEmpty()) {
            $this->warn('No outlets found.');
            return 0;
        }

        foreach ($outlets as $outlet) {
            $this->info("Processing outlet: {$outlet->nama} (Schema: {$outlet->schema_name})");
            try {
                $this->createTables($outlet->schema_name);
                $this->info("✅ Station tables created for {$outlet->nama}");
            } catch (\Exception $e) {
                $this->error("❌ Failed for {$outlet->nama}: " . $e->getMessage());
            }
        }

        return 0;
    }

    private function createTables($schema)
    {
        // 1. Stations table (dinamis, bisa diedit per outlet)
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.stations (
                id SERIAL PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                deskripsi TEXT,
                warna VARCHAR(20) DEFAULT '#3b82f6',
                icon VARCHAR(50) DEFAULT 'pi pi-box',
                is_active BOOLEAN DEFAULT true,
                urutan INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL
            )
        ");
        $this->info("  ✓ stations table ready");

        // 2. Tambah kolom station_id ke menu (jika belum ada)
        $colExists = DB::selectOne("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = ? AND table_name = 'menu' AND column_name = 'station_id'
        ", [$schema]);

        if (!$colExists) {
            DB::statement("ALTER TABLE {$schema}.menu ADD COLUMN station_id INTEGER REFERENCES {$schema}.stations(id) ON DELETE SET NULL");
            $this->info("  ✓ Added station_id to menu");
        } else {
            $this->warn("  - station_id already exists in menu");
        }

        // 3. Tambah kolom status ke order_items (jika belum ada)
        $statusExists = DB::selectOne("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = ? AND table_name = 'order_items' AND column_name = 'status'
        ", [$schema]);

        if (!$statusExists) {
            DB::statement("ALTER TABLE {$schema}.order_items ADD COLUMN status VARCHAR(20) DEFAULT 'pending'");
            DB::statement("ALTER TABLE {$schema}.order_items ADD COLUMN confirmed_at TIMESTAMP NULL");
            DB::statement("ALTER TABLE {$schema}.order_items ADD COLUMN confirmed_by BIGINT NULL");
            $this->info("  ✓ Added status, confirmed_at, confirmed_by to order_items");
        } else {
            $this->warn("  - status already exists in order_items");
        }

        // 4. Tambah kolom status ke orders untuk tracking KDS (jika belum ada)
        $orderStatusExists = DB::selectOne("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = ? AND table_name = 'orders' AND column_name = 'kitchen_status'
        ", [$schema]);

        if (!$orderStatusExists) {
            DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN kitchen_status VARCHAR(20) DEFAULT 'pending'");
            $this->info("  ✓ Added kitchen_status to orders");
        } else {
            $this->warn("  - kitchen_status already exists in orders");
        }

        // 5. Index
        DB::statement("CREATE INDEX IF NOT EXISTS idx_stations_active ON {$schema}.stations(is_active)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_menu_station ON {$schema}.menu(station_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_order_items_status ON {$schema}.order_items(status)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_orders_kitchen_status ON {$schema}.orders(kitchen_status)");

        // 6. Seed default stations jika belum ada
        $count = DB::selectOne("SELECT COUNT(*) as cnt FROM {$schema}.stations WHERE deleted_at IS NULL");
        if ($count->cnt == 0) {
            DB::statement("
                INSERT INTO {$schema}.stations (nama, deskripsi, warna, icon, urutan) VALUES
                ('Kitchen', 'Dapur - makanan', '#ef4444', 'pi pi-fire', 1),
                ('Bar', 'Bar - minuman', '#3b82f6', 'pi pi-glass-cocktail', 2)
            ");
            $this->info("  ✓ Seeded default stations (Kitchen, Bar)");
        }
    }
}
