<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateStockOpnameTables extends Command
{
    protected $signature = 'outlets:create-stock-opname-tables';
    protected $description = 'Create stock opname tables in all outlet schemas';

    public function handle()
    {
        $outlets = DB::table('outlets')->get();

        foreach ($outlets as $outlet) {
            $this->info("Creating stock opname tables for outlet: {$outlet->name} (schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Stock Opname Header Table
                DB::statement("
                    CREATE TABLE IF NOT EXISTS stock_opname (
                        id SERIAL PRIMARY KEY,
                        kode VARCHAR(50) UNIQUE NOT NULL,
                        tanggal_mulai DATE NOT NULL,
                        tanggal_selesai DATE NOT NULL,
                        status VARCHAR(20) DEFAULT 'draft',
                        pic_name VARCHAR(100),
                        pic_user_id INTEGER,
                        notes TEXT,
                        total_items INTEGER DEFAULT 0,
                        total_difference_value DECIMAL(15,2) DEFAULT 0,
                        approved_by INTEGER,
                        approved_at TIMESTAMP,
                        approval_notes TEXT,
                        created_by INTEGER,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        deleted_at TIMESTAMP
                    )
                ");

                // Stock Opname Detail Table
                DB::statement("
                    CREATE TABLE IF NOT EXISTS stock_opname_detail (
                        id SERIAL PRIMARY KEY,
                        stock_opname_id INTEGER NOT NULL,
                        bahan_baku_id INTEGER NOT NULL,
                        stock_location_id BIGINT,
                        system_stock DECIMAL(10,2) NOT NULL,
                        physical_stock DECIMAL(10,2),
                        difference DECIMAL(10,2),
                        difference_value DECIMAL(15,2),
                        notes TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (bahan_baku_id) REFERENCES bahan_baku(id) ON DELETE CASCADE
                    )
                ");
                // Backfill column for older schemas
                DB::statement("ALTER TABLE stock_opname_detail ADD COLUMN IF NOT EXISTS stock_location_id BIGINT");

                // Create indexes
                DB::statement("CREATE INDEX IF NOT EXISTS idx_stock_opname_status ON stock_opname(status)");
                DB::statement("CREATE INDEX IF NOT EXISTS idx_stock_opname_tanggal ON stock_opname(tanggal_mulai, tanggal_selesai)");
                DB::statement("CREATE INDEX IF NOT EXISTS idx_stock_opname_detail_opname ON stock_opname_detail(stock_opname_id)");
                DB::statement("CREATE INDEX IF NOT EXISTS idx_stock_opname_detail_location ON stock_opname_detail(stock_location_id)");
                
                $this->info("✓ Successfully created tables for {$outlet->name}");
            } catch (\Exception $e) {
                $this->error("✗ Error creating tables for {$outlet->name}: {$e->getMessage()}");
            }
        }
        
        DB::statement("SET search_path TO public");
        
        $this->info("\nStock opname tables creation completed!");
        
        return 0;
    }
}
