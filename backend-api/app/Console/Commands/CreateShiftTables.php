<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

class CreateShiftTables extends Command
{
    protected $signature = 'outlet:create-shift-tables {--outlet=}';
    protected $description = 'Create shift management tables for outlet schemas';

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
            $this->info("Creating shift tables for outlet: {$outlet->nama} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // 1. Shifts Table (Master shift definitions)
                if (!$this->tableExists('shifts')) {
                    DB::statement("
                        CREATE TABLE shifts (
                            id SERIAL PRIMARY KEY,
                            name VARCHAR(100) NOT NULL,
                            code VARCHAR(20) NOT NULL UNIQUE,
                            start_time TIME NOT NULL,
                            end_time TIME NOT NULL,
                            color VARCHAR(7) DEFAULT '#3b82f6',
                            description TEXT,
                            is_active BOOLEAN DEFAULT true,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $this->info('  ✓ Created shifts table');
                }

                // 2. Shift Assignments Table (Who works which shift on which date)
                if (!$this->tableExists('shift_assignments')) {
                    DB::statement("
                        CREATE TABLE shift_assignments (
                            id SERIAL PRIMARY KEY,
                            user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                            shift_id INTEGER NOT NULL REFERENCES shifts(id) ON DELETE CASCADE,
                            date DATE NOT NULL,
                            status VARCHAR(20) DEFAULT 'scheduled', -- scheduled, completed, absent, swapped
                            notes TEXT,
                            created_by INTEGER REFERENCES outlet_users(id),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE(user_id, date)
                        )
                    ");
                    
                    DB::statement("CREATE INDEX idx_shift_assignments_date ON shift_assignments(date)");
                    DB::statement("CREATE INDEX idx_shift_assignments_user ON shift_assignments(user_id)");
                    DB::statement("CREATE INDEX idx_shift_assignments_shift ON shift_assignments(shift_id)");
                    $this->info('  ✓ Created shift_assignments table');
                }

                // 3. Shift Swap Requests Table
                if (!$this->tableExists('shift_swap_requests')) {
                    DB::statement("
                        CREATE TABLE shift_swap_requests (
                            id SERIAL PRIMARY KEY,
                            requester_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                            requester_assignment_id INTEGER NOT NULL REFERENCES shift_assignments(id) ON DELETE CASCADE,
                            target_id INTEGER REFERENCES outlet_users(id) ON DELETE CASCADE,
                            target_assignment_id INTEGER REFERENCES shift_assignments(id) ON DELETE CASCADE,
                            reason TEXT NOT NULL,
                            status VARCHAR(20) DEFAULT 'pending', -- pending, approved, rejected, cancelled
                            reviewed_by INTEGER REFERENCES outlet_users(id),
                            reviewed_at TIMESTAMP,
                            review_notes TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $this->info('  ✓ Created shift_swap_requests table');
                }

                // 4. Shift Templates Table (Recurring shift patterns)
                if (!$this->tableExists('shift_templates')) {
                    DB::statement("
                        CREATE TABLE shift_templates (
                            id SERIAL PRIMARY KEY,
                            name VARCHAR(100) NOT NULL,
                            description TEXT,
                            is_active BOOLEAN DEFAULT true,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $this->info('  ✓ Created shift_templates table');
                }

                // 5. Shift Template Details Table
                if (!$this->tableExists('shift_template_details')) {
                    DB::statement("
                        CREATE TABLE shift_template_details (
                            id SERIAL PRIMARY KEY,
                            template_id INTEGER NOT NULL REFERENCES shift_templates(id) ON DELETE CASCADE,
                            user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                            shift_id INTEGER NOT NULL REFERENCES shifts(id) ON DELETE CASCADE,
                            day_of_week INTEGER NOT NULL, -- 0=Sunday, 1=Monday, ..., 6=Saturday
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $this->info('  ✓ Created shift_template_details table');
                }

                DB::statement("SET search_path TO public");
                $this->info("  ✓ Successfully created shift tables for {$outlet->nama}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->error("  ✗ Error for {$outlet->nama}: {$e->getMessage()}\n");
            }
        }

        $this->info('Shift tables creation completed!');
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
