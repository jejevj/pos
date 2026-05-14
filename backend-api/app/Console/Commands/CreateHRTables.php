<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

class CreateHRTables extends Command
{
    protected $signature = 'outlet:create-hr-tables {--outlet=}';
    protected $description = 'Create HR management tables (attendance, payroll, leave) for outlet schemas';

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
            $this->info("Creating HR tables for outlet: {$outlet->nama} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // 1. Employee Info Table (extend outlet_users)
                if (!$this->tableExists('employee_info')) {
                    DB::statement("
                        CREATE TABLE employee_info (
                            id SERIAL PRIMARY KEY,
                            user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                            employee_code VARCHAR(50) UNIQUE,
                            join_date DATE NOT NULL,
                            employment_type VARCHAR(20) DEFAULT 'full_time', -- full_time, part_time, contract
                            basic_salary DECIMAL(15,2) DEFAULT 0,
                            hourly_rate DECIMAL(10,2) DEFAULT 0,
                            overtime_rate DECIMAL(10,2) DEFAULT 0,
                            bank_name VARCHAR(100),
                            bank_account VARCHAR(50),
                            bank_account_name VARCHAR(100),
                            emergency_contact_name VARCHAR(100),
                            emergency_contact_phone VARCHAR(20),
                            address TEXT,
                            day_off INTEGER DEFAULT 0, -- 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
                            is_active BOOLEAN DEFAULT true,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $this->info('  ✓ Created employee_info table');
                }

                // 2. Attendance Table
                if (!$this->tableExists('attendances')) {
                    DB::statement("
                        CREATE TABLE attendances (
                            id SERIAL PRIMARY KEY,
                            user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                            date DATE NOT NULL,
                            clock_in TIMESTAMP,
                            clock_out TIMESTAMP,
                            clock_in_photo TEXT,
                            clock_out_photo TEXT,
                            clock_in_location TEXT,
                            clock_out_location TEXT,
                            clock_in_notes TEXT,
                            clock_out_notes TEXT,
                            work_hours DECIMAL(5,2) DEFAULT 0,
                            overtime_hours DECIMAL(5,2) DEFAULT 0,
                            status VARCHAR(20) DEFAULT 'present', -- present, late, absent, leave, half_day
                            approved_by INTEGER REFERENCES outlet_users(id),
                            approved_at TIMESTAMP,
                            notes TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE(user_id, date)
                        )
                    ");
                    
                    DB::statement("CREATE INDEX idx_attendances_user_date ON attendances(user_id, date)");
                    DB::statement("CREATE INDEX idx_attendances_date ON attendances(date)");
                    $this->info('  ✓ Created attendances table');
                } else {
                    // Add photo columns if they don't exist
                    $this->addColumnIfNotExists('attendances', 'clock_in_photo', 'TEXT');
                    $this->addColumnIfNotExists('attendances', 'clock_out_photo', 'TEXT');
                    
                    // Update location columns to TEXT for JSON storage
                    DB::statement("ALTER TABLE attendances ALTER COLUMN clock_in_location TYPE TEXT");
                    DB::statement("ALTER TABLE attendances ALTER COLUMN clock_out_location TYPE TEXT");
                }

                // 3. Leave Requests Table
                if (!$this->tableExists('leave_requests')) {
                    DB::statement("
                        CREATE TABLE leave_requests (
                            id SERIAL PRIMARY KEY,
                            user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                            leave_type VARCHAR(20) NOT NULL, -- annual, sick, unpaid, emergency
                            start_date DATE NOT NULL,
                            end_date DATE NOT NULL,
                            total_days INTEGER NOT NULL,
                            reason TEXT NOT NULL,
                            attachment VARCHAR(255),
                            status VARCHAR(20) DEFAULT 'pending', -- pending, approved, rejected, cancelled
                            reviewed_by INTEGER REFERENCES outlet_users(id),
                            reviewed_at TIMESTAMP,
                            review_notes TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    
                    DB::statement("CREATE INDEX idx_leave_requests_user ON leave_requests(user_id)");
                    DB::statement("CREATE INDEX idx_leave_requests_status ON leave_requests(status)");
                    $this->info('  ✓ Created leave_requests table');
                }

                // 4. Leave Balances Table
                if (!$this->tableExists('leave_balances')) {
                    DB::statement("
                        CREATE TABLE leave_balances (
                            id SERIAL PRIMARY KEY,
                            user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                            year INTEGER NOT NULL,
                            leave_type VARCHAR(20) NOT NULL,
                            total_days INTEGER DEFAULT 0,
                            used_days INTEGER DEFAULT 0,
                            remaining_days INTEGER DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE(user_id, year, leave_type)
                        )
                    ");
                    $this->info('  ✓ Created leave_balances table');
                }

                // 5. Payroll Table
                if (!$this->tableExists('payrolls')) {
                    DB::statement("
                        CREATE TABLE payrolls (
                            id SERIAL PRIMARY KEY,
                            user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                            period_month INTEGER NOT NULL,
                            period_year INTEGER NOT NULL,
                            basic_salary DECIMAL(15,2) DEFAULT 0,
                            overtime_pay DECIMAL(15,2) DEFAULT 0,
                            allowances DECIMAL(15,2) DEFAULT 0,
                            bonuses DECIMAL(15,2) DEFAULT 0,
                            deductions DECIMAL(15,2) DEFAULT 0,
                            gross_salary DECIMAL(15,2) DEFAULT 0,
                            net_salary DECIMAL(15,2) DEFAULT 0,
                            work_days INTEGER DEFAULT 0,
                            present_days INTEGER DEFAULT 0,
                            absent_days INTEGER DEFAULT 0,
                            leave_days INTEGER DEFAULT 0,
                            late_days INTEGER DEFAULT 0,
                            overtime_hours DECIMAL(5,2) DEFAULT 0,
                            status VARCHAR(20) DEFAULT 'draft', -- draft, approved, paid
                            payment_date DATE,
                            payment_method VARCHAR(50),
                            notes TEXT,
                            created_by INTEGER REFERENCES outlet_users(id),
                            approved_by INTEGER REFERENCES outlet_users(id),
                            approved_at TIMESTAMP,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE(user_id, period_month, period_year)
                        )
                    ");
                    
                    DB::statement("CREATE INDEX idx_payrolls_user ON payrolls(user_id)");
                    DB::statement("CREATE INDEX idx_payrolls_period ON payrolls(period_year, period_month)");
                    $this->info('  ✓ Created payrolls table');
                }

                // 6. Payroll Details Table
                if (!$this->tableExists('payroll_details')) {
                    DB::statement("
                        CREATE TABLE payroll_details (
                            id SERIAL PRIMARY KEY,
                            payroll_id INTEGER NOT NULL REFERENCES payrolls(id) ON DELETE CASCADE,
                            type VARCHAR(20) NOT NULL, -- allowance, bonus, deduction
                            description VARCHAR(255) NOT NULL,
                            amount DECIMAL(15,2) NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $this->info('  ✓ Created payroll_details table');
                }

                // 7. Payroll Settings Table
                if (!$this->tableExists('payroll_settings')) {
                    DB::statement("
                        CREATE TABLE payroll_settings (
                            id SERIAL PRIMARY KEY,
                            work_days_per_month INTEGER DEFAULT 22,
                            work_hours_per_day DECIMAL(4,1) DEFAULT 8.0,
                            overtime_multiplier DECIMAL(3,1) DEFAULT 1.5,
                            late_tolerance_minutes INTEGER DEFAULT 15,
                            annual_leave_days INTEGER DEFAULT 12,
                            sick_leave_days INTEGER DEFAULT 12,
                            tax_percentage DECIMAL(5,2) DEFAULT 0,
                            attendance_location_lat DECIMAL(10,8),
                            attendance_location_lng DECIMAL(11,8),
                            attendance_radius INTEGER DEFAULT 100,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    
                    // Insert default settings
                    DB::statement("
                        INSERT INTO payroll_settings (work_days_per_month, work_hours_per_day, overtime_multiplier, late_tolerance_minutes, annual_leave_days, sick_leave_days, tax_percentage, attendance_radius) 
                        VALUES (22, 8.0, 1.5, 15, 12, 12, 0, 100)
                    ");
                    
                    $this->info('  ✓ Created payroll_settings table');
                } else {
                    // Add attendance location columns if they don't exist
                    $this->addColumnIfNotExists('payroll_settings', 'attendance_location_lat', 'DECIMAL(10,8)');
                    $this->addColumnIfNotExists('payroll_settings', 'attendance_location_lng', 'DECIMAL(11,8)');
                    $this->addColumnIfNotExists('payroll_settings', 'attendance_radius', 'INTEGER DEFAULT 100');
                }

                DB::statement("SET search_path TO public");
                $this->info("  ✓ Successfully created HR tables for {$outlet->nama}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->error("  ✗ Error for {$outlet->nama}: {$e->getMessage()}\n");
            }
        }

        $this->info('HR tables creation completed!');
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
