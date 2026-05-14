<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

class AddDayOffColumn extends Command
{
    protected $signature = 'outlet:add-day-off-column {--outlet=}';
    protected $description = 'Add day_off column to employee_info table in outlet schemas';

    public function handle()
    {
        $outletId = $this->option('outlet');
        
        if ($outletId) {
            $outlet = Outlet::find($outletId);
            if (!$outlet) {
                $this->error("Outlet with ID {$outletId} not found");
                return 1;
            }
            $outlets = collect([$outlet]);
        } else {
            $outlets = Outlet::all();
        }

        if ($outlets->isEmpty()) {
            $this->error('No outlets found');
            return 1;
        }

        foreach ($outlets as $outlet) {
            $this->info("Processing outlet: {$outlet->nama} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Check if employee_info table exists
                $tableExists = DB::select("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = current_schema()
                        AND table_name = 'employee_info'
                    )
                ");
                
                if (!$tableExists[0]->exists) {
                    $this->warn("  ⚠ employee_info table does not exist, skipping");
                    DB::statement("SET search_path TO public");
                    continue;
                }
                
                // Check if day_off column already exists
                $columnExists = DB::select("
                    SELECT EXISTS (
                        SELECT FROM information_schema.columns 
                        WHERE table_schema = current_schema()
                        AND table_name = 'employee_info' 
                        AND column_name = 'day_off'
                    )
                ");
                
                if ($columnExists[0]->exists) {
                    $this->info("  ✓ day_off column already exists");
                } else {
                    // Add day_off column
                    DB::statement("
                        ALTER TABLE employee_info 
                        ADD COLUMN day_off INTEGER DEFAULT 0
                    ");
                    
                    // Add comment
                    DB::statement("
                        COMMENT ON COLUMN employee_info.day_off IS 
                        '0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday'
                    ");
                    
                    // Add check constraint
                    DB::statement("
                        ALTER TABLE employee_info 
                        ADD CONSTRAINT employee_info_day_off_check 
                        CHECK (day_off >= 0 AND day_off <= 6)
                    ");
                    
                    $this->info("  ✓ Added day_off column with constraint");
                }
                
                // Check if payroll_settings table exists and add columns if needed
                $settingsTableExists = DB::select("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = current_schema()
                        AND table_name = 'payroll_settings'
                    )
                ");
                
                if ($settingsTableExists[0]->exists) {
                    // Check and add weekly_day_off_enabled column
                    $weeklyDayOffExists = DB::select("
                        SELECT EXISTS (
                            SELECT FROM information_schema.columns 
                            WHERE table_schema = current_schema()
                            AND table_name = 'payroll_settings' 
                            AND column_name = 'weekly_day_off_enabled'
                        )
                    ");
                    
                    if (!$weeklyDayOffExists[0]->exists) {
                        DB::statement("
                            ALTER TABLE payroll_settings 
                            ADD COLUMN weekly_day_off_enabled BOOLEAN DEFAULT true
                        ");
                        $this->info("  ✓ Added weekly_day_off_enabled column to payroll_settings");
                    }
                    
                    // Check and add min_staff_per_role column
                    $minStaffExists = DB::select("
                        SELECT EXISTS (
                            SELECT FROM information_schema.columns 
                            WHERE table_schema = current_schema()
                            AND table_name = 'payroll_settings' 
                            AND column_name = 'min_staff_per_role'
                        )
                    ");
                    
                    if (!$minStaffExists[0]->exists) {
                        DB::statement("
                            ALTER TABLE payroll_settings 
                            ADD COLUMN min_staff_per_role INTEGER DEFAULT 1
                        ");
                        $this->info("  ✓ Added min_staff_per_role column to payroll_settings");
                    }
                }
                
                DB::statement("SET search_path TO public");
                $this->info("  ✓ Completed\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->error("  ✗ Error: {$e->getMessage()}\n");
            }
        }

        $this->info('Migration completed!');
        return 0;
    }
}
