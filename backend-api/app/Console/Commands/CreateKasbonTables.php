<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateKasbonTables extends Command
{
    protected $signature = 'create:kasbon-tables';
    protected $description = 'Create kasbon (salary advance) tables for all outlet schemas';

    public function handle()
    {
        $this->info('Creating kasbon tables...');

        $outlets = DB::table('outlets')->get();

        foreach ($outlets as $outlet) {
            $this->info("Processing outlet: {$outlet->name} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                $this->createKasbonSettingsTable();
                $this->createKasbonTable();
                
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

    private function createKasbonSettingsTable()
    {
        if (!$this->tableExists('kasbon_settings')) {
            DB::statement("
                CREATE TABLE kasbon_settings (
                    id SERIAL PRIMARY KEY,
                    max_percentage DECIMAL(5,2) DEFAULT 50.00,
                    require_approval BOOLEAN DEFAULT true,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Insert default settings
            DB::statement("
                INSERT INTO kasbon_settings (max_percentage, require_approval) 
                VALUES (50.00, true)
            ");
            
            $this->info('  ✓ Created kasbon_settings table');
        }
    }

    private function createKasbonTable()
    {
        if (!$this->tableExists('kasbon')) {
            DB::statement("
                CREATE TABLE kasbon (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                    request_date DATE NOT NULL,
                    amount DECIMAL(15,2) NOT NULL,
                    reason TEXT,
                    status VARCHAR(20) DEFAULT 'pending',
                    approved_by INTEGER,
                    approved_at TIMESTAMP,
                    approval_proof TEXT,
                    rejection_reason TEXT,
                    repayment_status VARCHAR(20) DEFAULT 'unpaid',
                    repayment_amount DECIMAL(15,2) DEFAULT 0,
                    repayment_date DATE,
                    repayment_proof TEXT,
                    notes TEXT,
                    created_by INTEGER NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $this->info('  ✓ Created kasbon table');
        } else {
            // Add columns if they don't exist
            $this->addColumnIfNotExists('kasbon', 'approval_proof', 'TEXT');
            $this->addColumnIfNotExists('kasbon', 'repayment_proof', 'TEXT');
        }
    }

    private function addColumnIfNotExists($table, $column, $type)
    {
        $exists = DB::select("
            SELECT EXISTS (
                SELECT FROM information_schema.columns 
                WHERE table_schema = current_schema()
                AND table_name = ?
                AND column_name = ?
            )
        ", [$table, $column]);
        
        if (!$exists[0]->exists) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
            $this->info("  ✓ Added column {$column} to {$table}");
        }
    }
}
