<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class CreateEmployeeBeverageTables extends Command
{
    protected $signature = 'outlets:create-employee-beverage-tables {--outlet-id=}';
    protected $description = 'Create employee beverage allowance tables for outlets';

    public function handle()
    {
        $outletId = $this->option('outlet-id');
        
        if ($outletId) {
            $outlets = Outlet::where('id', $outletId)->get();
        } else {
            $outlets = Outlet::all();
        }
        
        if ($outlets->isEmpty()) {
            $this->error('No outlets found');
            return 1;
        }

        foreach ($outlets as $outlet) {
            $this->info("Creating employee beverage tables for outlet: {$outlet->name} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Table 1: Employee Beverage Settings
                if (!$this->tableExists('employee_beverage_settings')) {
                    DB::statement("
                        CREATE TABLE employee_beverage_settings (
                            id SERIAL PRIMARY KEY,
                            daily_quota INTEGER NOT NULL DEFAULT 1,
                            is_active BOOLEAN DEFAULT true,
                            reset_time TIME DEFAULT '00:00:00',
                            notes TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    
                    // Insert default settings
                    DB::statement("
                        INSERT INTO employee_beverage_settings (daily_quota, is_active, reset_time, notes)
                        VALUES (1, true, '00:00:00', 'Default employee beverage allowance settings')
                    ");
                    
                    $this->info("  ✓ Created employee_beverage_settings table");
                }
                
                // Table 2: Allowed Beverages (Menu items that can be claimed)
                if (!$this->tableExists('employee_allowed_beverages')) {
                    DB::statement("
                        CREATE TABLE employee_allowed_beverages (
                            id SERIAL PRIMARY KEY,
                            menu_id INTEGER NOT NULL,
                            is_active BOOLEAN DEFAULT true,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE CASCADE
                        )
                    ");
                    $this->info("  ✓ Created employee_allowed_beverages table");
                }
                
                // Table 3: Employee Beverage Claims
                if (!$this->tableExists('employee_beverage_claims')) {
                    DB::statement("
                        CREATE TABLE employee_beverage_claims (
                            id SERIAL PRIMARY KEY,
                            user_id INTEGER NOT NULL,
                            menu_id INTEGER NOT NULL,
                            claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            claimed_date DATE DEFAULT CURRENT_DATE,
                            notes TEXT,
                            created_by INTEGER,
                            FOREIGN KEY (user_id) REFERENCES outlet_users(id) ON DELETE CASCADE,
                            FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE CASCADE
                        )
                    ");
                    
                    // Create index for faster queries
                    DB::statement("
                        CREATE INDEX idx_beverage_claims_user_date 
                        ON employee_beverage_claims(user_id, claimed_date)
                    ");
                    
                    $this->info("  ✓ Created employee_beverage_claims table");
                }
                
                DB::statement("SET search_path TO public");
                $this->info("  ✓ Completed for {$outlet->name}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->error("  ✗ Error: {$e->getMessage()}\n");
            }
        }

        $this->info('Employee beverage tables creation completed!');
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
}
