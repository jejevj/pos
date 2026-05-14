<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;
use Carbon\Carbon;

class EmployeeInfoSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = Outlet::all();
        
        if ($outlets->isEmpty()) {
            $this->command->error('No outlets found');
            return;
        }

        foreach ($outlets as $outlet) {
            $this->command->info("Seeding employee info for outlet: {$outlet->nama}");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Get all users except owner
                $users = DB::table('outlet_users')
                    ->join('user_roles', 'outlet_users.id', '=', 'user_roles.user_id')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->where('roles.name', '!=', 'owner')
                    ->where('outlet_users.is_active', true)
                    ->select('outlet_users.id', 'outlet_users.name', 'roles.name as role_name')
                    ->get();

                $insertedCount = 0;
                $currentYear = Carbon::now()->year;

                foreach ($users as $user) {
                    // Check if employee info already exists
                    $exists = DB::table('employee_info')
                        ->where('user_id', $user->id)
                        ->exists();
                    
                    if (!$exists) {
                        // Generate employee code
                        $employeeCode = 'EMP' . str_pad($user->id, 4, '0', STR_PAD_LEFT);
                        
                        // Set salary based on role
                        $salaryMap = [
                            'spv' => ['basic' => 6000000, 'hourly' => 0, 'overtime' => 50000],
                            'manager' => ['basic' => 5000000, 'hourly' => 0, 'overtime' => 40000],
                            'cashier' => ['basic' => 3500000, 'hourly' => 0, 'overtime' => 30000],
                            'barista' => ['basic' => 3500000, 'hourly' => 0, 'overtime' => 30000],
                            'waitress' => ['basic' => 3000000, 'hourly' => 0, 'overtime' => 25000],
                            'kitchen_staff' => ['basic' => 3500000, 'hourly' => 0, 'overtime' => 30000],
                        ];
                        
                        $salary = $salaryMap[$user->role_name] ?? ['basic' => 3000000, 'hourly' => 0, 'overtime' => 25000];
                        
                        // Insert employee info
                        DB::table('employee_info')->insert([
                            'user_id' => $user->id,
                            'employee_code' => $employeeCode,
                            'join_date' => Carbon::now()->subMonths(rand(1, 12)),
                            'employment_type' => 'full_time',
                            'basic_salary' => $salary['basic'],
                            'hourly_rate' => $salary['hourly'],
                            'overtime_rate' => $salary['overtime'],
                            'bank_name' => 'BCA',
                            'bank_account' => '1234567' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                            'bank_account_name' => $user->name,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        // Initialize leave balance for current year
                        $leaveBalanceExists = DB::table('leave_balances')
                            ->where('user_id', $user->id)
                            ->where('year', $currentYear)
                            ->where('leave_type', 'annual')
                            ->exists();
                        
                        if (!$leaveBalanceExists) {
                            DB::table('leave_balances')->insert([
                                'user_id' => $user->id,
                                'year' => $currentYear,
                                'leave_type' => 'annual',
                                'total_days' => 12,
                                'used_days' => 0,
                                'remaining_days' => 12,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        
                        $insertedCount++;
                    }
                }

                DB::statement("SET search_path TO public");
                $this->command->info("  ✓ Seeded {$insertedCount} employee records for {$outlet->nama}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->command->error("  ✗ Error: {$e->getMessage()}\n");
            }
        }

        $this->command->info('Employee info seeding completed!');
        $this->command->info('');
        $this->command->info('=== Salary Information ===');
        $this->command->info('Supervisor: Rp 6,000,000 (OT: Rp 50,000/hour)');
        $this->command->info('Manager: Rp 5,000,000 (OT: Rp 40,000/hour)');
        $this->command->info('Cashier: Rp 3,500,000 (OT: Rp 30,000/hour)');
        $this->command->info('Barista: Rp 3,500,000 (OT: Rp 30,000/hour)');
        $this->command->info('Kitchen Staff: Rp 3,500,000 (OT: Rp 30,000/hour)');
        $this->command->info('Waitress: Rp 3,000,000 (OT: Rp 25,000/hour)');
        $this->command->info('');
        $this->command->info('Annual Leave: 12 days per year');
    }
}
