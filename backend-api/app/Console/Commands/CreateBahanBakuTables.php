<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class CreateBahanBakuTables extends Command
{
    protected $signature = 'outlets:create-bahan-baku-tables {--outlet= : Specific outlet ID}';
    protected $description = 'Create bahan baku related tables in outlet schemas';

    public function handle()
    {
        $this->info('Creating bahan baku tables in outlet schemas...');
        
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
                $this->info("✅ Tables created successfully for {$outlet->name}");
            } catch (\Exception $e) {
                $this->error("❌ Failed for {$outlet->name}: " . $e->getMessage());
            }
        }

        return 0;
    }

    private function createTables($schema)
    {
        // 1. Kategori Bahan Baku
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.kategori_bahan_baku (
                id SERIAL PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                deskripsi TEXT,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");

        // 2. Satuan (Units)
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.satuan (
                id SERIAL PRIMARY KEY,
                nama VARCHAR(50) NOT NULL,
                singkatan VARCHAR(10) NOT NULL,
                tipe VARCHAR(20) NOT NULL,
                is_base_unit BOOLEAN DEFAULT false,
                conversion_to_base DECIMAL(10,4),
                deskripsi TEXT,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");

        // 3. Supplier
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.supplier (
                id SERIAL PRIMARY KEY,
                kode VARCHAR(50) UNIQUE NOT NULL,
                nama VARCHAR(200) NOT NULL,
                contact_person VARCHAR(100),
                phone VARCHAR(50),
                email VARCHAR(100),
                alamat TEXT,
                kota VARCHAR(100),
                provinsi VARCHAR(100),
                kode_pos VARCHAR(10),
                payment_terms VARCHAR(50),
                notes TEXT,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");

        // 4. Bahan Baku (Raw Materials)
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.bahan_baku (
                id SERIAL PRIMARY KEY,
                kode VARCHAR(50) UNIQUE NOT NULL,
                nama VARCHAR(200) NOT NULL,
                kategori_id INTEGER REFERENCES {$schema}.kategori_bahan_baku(id),
                satuan_id INTEGER REFERENCES {$schema}.satuan(id),
                satuan_pembelian_id INTEGER REFERENCES {$schema}.satuan(id),
                jumlah_per_unit_pembelian DECIMAL(10,4) DEFAULT 1,
                supplier_id INTEGER REFERENCES {$schema}.supplier(id),
                harga_beli DECIMAL(15,2) DEFAULT 0,
                minimum_stock DECIMAL(10,2) DEFAULT 0,
                current_stock DECIMAL(10,2) DEFAULT 0,
                lokasi_penyimpanan VARCHAR(100),
                expired_date DATE,
                gambar_url VARCHAR(255),
                deskripsi TEXT,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP,
                COMMENT ON COLUMN satuan_id IS 'Satuan dasar untuk penggunaan (base unit)',
                COMMENT ON COLUMN satuan_pembelian_id IS 'Satuan saat pembelian',
                COMMENT ON COLUMN jumlah_per_unit_pembelian IS 'Jumlah satuan dasar per unit pembelian (contoh: 1 galon = 20 liter)',
                COMMENT ON COLUMN harga_beli IS 'Harga per unit pembelian'
            )
        ");

        // 5. Stock History (untuk tracking perubahan stock)
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.stock_history (
                id SERIAL PRIMARY KEY,
                bahan_baku_id INTEGER REFERENCES {$schema}.bahan_baku(id),
                tipe VARCHAR(20) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL,
                stock_before DECIMAL(10,2) NOT NULL,
                stock_after DECIMAL(10,2) NOT NULL,
                reference_type VARCHAR(50),
                reference_id INTEGER,
                notes TEXT,
                created_by INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create indexes
        DB::statement("CREATE INDEX IF NOT EXISTS idx_bahan_baku_kategori ON {$schema}.bahan_baku(kategori_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_bahan_baku_supplier ON {$schema}.bahan_baku(supplier_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_bahan_baku_kode ON {$schema}.bahan_baku(kode)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_stock_history_bahan ON {$schema}.stock_history(bahan_baku_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_supplier_kode ON {$schema}.supplier(kode)");
    }
}
