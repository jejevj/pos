<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

class SetEmployeeDayOff extends Seeder
{
    public function run(): void
    {
        $outlets = Outlet::all();
        
        if ($outlets->isEmpty()) {
            $this->command->error('No outlets found');
            return;
        }

        foreach ($outlets as $outlet) {
            $this->command->info("Setting day off for outlet: {$outlet->nama}");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Get all employees grouped by role
                $employees = DB::table('outlet_users')
                    ->join('user_roles', 'outlet_users.id', '=', 'user_roles.user_id')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->leftJoin('employee_info', 'outlet_users.id', '=', 'employee_info.user_id')
                    ->where('outlet_users.is_active', true)
                    ->where('roles.name', '!=', 'owner')
                    ->whereNull('outlet_users.deleted_at')
                    ->select(
                        'outlet_users.id',
                        'outlet_users.name',
                        'roles.name as role_name',
                        'employee_info.user_id as has_employee_info'
                    )
                    ->orderBy('roles.name')
                    ->orderBy('outlet_users.id')
                    ->get();
                
                if ($employees->isEmpty()) {
                    $this->command->warn('  ⚠ No employees found');
                    DB::statement("SET search_path TO public");
                    continue;
                }
                
                // Group by role
                $employeesByRole = $employees->groupBy('role_name');
                
                $globalDayOffCounter = 0; // Global counter to distribute across all employees
                $updated = 0;
                
                foreach ($employeesByRole as $roleName => $roleEmployees) {
                    $this->command->info("  Processing role: {$roleName} ({$roleEmployees->count()} employees)");
                    
                    foreach ($roleEmployees as $index => $employee) {
                        // Strategy: Distribute day offs across the week
                        // For roles with multiple employees: each gets different day
                        // For roles with single employee: use global counter to avoid clustering
                        
                        if ($roleEmployees->count() > 1) {
                            // Multiple employees in role: distribute within role
                            $dayOff = $index % 7;
                        } else {
                            // Single employee in role: use global counter
                            $dayOff = $globalDayOffCounter % 7;
                            $globalDayOffCounter++;
                        }
                        
                        // Create or update employee_info
                        if ($employee->has_employee_info) {
                            DB::table('employee_info')
                                ->where('user_id', $employee->id)
                                ->update([
                                    'day_off' => $dayOff,
                                    'updated_at' => now()
                                ]);
                        } else {
                            DB::table('employee_info')->insert([
                                'user_id' => $employee->id,
                                'join_date' => now(),
                                'employment_type' => 'full_time',
                                'basic_salary' => 0,
                                'day_off' => $dayOff,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                        
                        $dayName = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$dayOff];
                        $this->command->info("    ✓ {$employee->name}: {$dayName}");
                        $updated++;
                    }
                }
                
                DB::statement("SET search_path TO public");
                $this->command->info("  ✓ Updated {$updated} employees\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->command->error("  ✗ Error: {$e->getMessage()}\n");
            }
        }

        $this->command->info('Day off setup completed!');
        $this->command->info('');
        $this->command->info('=== Distribution Strategy ===');
        $this->command->info('Employees in the same role have different day offs');
        $this->command->info('This ensures coverage for each role every day');
        $this->command->info('Example: If 2 Cashiers, one gets Sunday off, other gets Monday off');
    }
}
