<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreatePurchaseExpenseTables extends Command
{
    protected $signature = 'create:purchase-expense-tables';
    protected $description = 'Create purchase and expense tables for all outlet schemas';

    public function handle()
    {
        $this->info('Creating purchase and expense tables...');

        $outlets = DB::table('outlets')->get();

        foreach ($outlets as $outlet) {
            $this->info("Processing outlet: {$outlet->name} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                $this->createPurchasesTable();
                $this->createPurchaseItemsTable();
                $this->createExpensesTable();
                
                DB::statement("SET search_path TO public");
                
                $this->info("✓ Completed for {$outlet->name}");
            } catch (\Exception $e) {
                $this->error("✗ Error for {$outlet->name}: " . $e->getMessage());
                DB::statement("SET search_path TO public");
            }
        }

        $this->info('All done!');
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

    private function createPurchasesTable()
    {
        if (!$this->tableExists('purchases')) {
            DB::statement("
                CREATE TABLE purchases (
                    id SERIAL PRIMARY KEY,
                    purchase_code VARCHAR(50) UNIQUE NOT NULL,
                    supplier_id INTEGER,
                    supplier_name VARCHAR(255),
                    purchase_date DATE NOT NULL,
                    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                    payment_method VARCHAR(50),
                    payment_proof_url TEXT,
                    notes TEXT,
                    status VARCHAR(20) DEFAULT 'completed',
                    created_by INTEGER NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $this->info('  ✓ Created purchases table');
        }
    }

    private function createPurchaseItemsTable()
    {
        if (!$this->tableExists('purchase_items')) {
            DB::statement("
                CREATE TABLE purchase_items (
                    id SERIAL PRIMARY KEY,
                    purchase_id INTEGER NOT NULL REFERENCES purchases(id) ON DELETE CASCADE,
                    bahan_baku_id INTEGER NOT NULL REFERENCES bahan_baku(id) ON DELETE RESTRICT,
                    quantity DECIMAL(10,2) NOT NULL,
                    unit_price DECIMAL(15,2) NOT NULL,
                    subtotal DECIMAL(15,2) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $this->info('  ✓ Created purchase_items table');
        }
    }

    private function createExpensesTable()
    {
        if (!$this->tableExists('expenses')) {
            DB::statement("
                CREATE TABLE expenses (
                    id SERIAL PRIMARY KEY,
                    expense_code VARCHAR(50) UNIQUE NOT NULL,
                    expense_date DATE NOT NULL,
                    category VARCHAR(100) NOT NULL,
                    description TEXT NOT NULL,
                    amount DECIMAL(15,2) NOT NULL,
                    payment_method VARCHAR(50),
                    payment_proof_url TEXT,
                    notes TEXT,
                    created_by INTEGER NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $this->info('  ✓ Created expenses table');
        }
    }
}
