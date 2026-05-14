<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;
use Carbon\Carbon;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = Outlet::all();
        
        if ($outlets->isEmpty()) {
            $this->command->error('No outlets found');
            return;
        }

        foreach ($outlets as $outlet) {
            $this->command->info("Seeding shifts for outlet: {$outlet->nama}");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // 1. Create default shifts
                $morningShift = DB::table('shifts')->where('code', 'morning')->first();
                if (!$morningShift) {
                    $morningShiftId = DB::table('shifts')->insertGetId([
                        'name' => 'Shift Pagi',
                        'code' => 'morning',
                        'start_time' => '07:00:00',
                        'end_time' => '15:00:00',
                        'color' => '#f59e0b',
                        'description' => 'Shift pagi dari jam 7 pagi sampai 3 sore',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->command->info('  ✓ Created morning shift');
                } else {
                    $morningShiftId = $morningShift->id;
                }

                $nightShift = DB::table('shifts')->where('code', 'night')->first();
                if (!$nightShift) {
                    $nightShiftId = DB::table('shifts')->insertGetId([
                        'name' => 'Shift Malam',
                        'code' => 'night',
                        'start_time' => '15:00:00',
                        'end_time' => '23:00:00',
                        'color' => '#3b82f6',
                        'description' => 'Shift malam dari jam 3 sore sampai 11 malam',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->command->info('  ✓ Created night shift');
                } else {
                    $nightShiftId = $nightShift->id;
                }

                // 2. Get active employees
                $employees = DB::table('outlet_users')
                    ->join('user_roles', 'outlet_users.id', '=', 'user_roles.user_id')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->where('outlet_users.is_active', true)
                    ->where('roles.name', '!=', 'owner')
                    ->whereNull('outlet_users.deleted_at')
                    ->select('outlet_users.id', 'outlet_users.name', 'roles.name as role_name')
                    ->get();

                if ($employees->isEmpty()) {
                    $this->command->warn('  ⚠ No employees found, skipping shift assignments');
                    DB::statement("SET search_path TO public");
                    continue;
                }

                // 3. Create shift assignments for next 30 days
                $startDate = Carbon::now()->startOfDay();
                $endDate = Carbon::now()->addDays(30);
                $assignedCount = 0;

                // Distribute employees between shifts
                $morningEmployees = $employees->filter(function($emp, $index) {
                    return $index % 2 === 0; // Even index = morning
                });
                
                $nightEmployees = $employees->filter(function($emp, $index) {
                    return $index % 2 !== 0; // Odd index = night
                });

                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                    $dateStr = $date->toDateString();
                    
                    // Assign morning shift employees
                    foreach ($morningEmployees as $employee) {
                        $exists = DB::table('shift_assignments')
                            ->where('user_id', $employee->id)
                            ->where('date', $dateStr)
                            ->exists();
                        
                        if (!$exists) {
                            DB::table('shift_assignments')->insert([
                                'user_id' => $employee->id,
                                'shift_id' => $morningShiftId,
                                'date' => $dateStr,
                                'status' => 'scheduled',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $assignedCount++;
                        }
                    }
                    
                    // Assign night shift employees
                    foreach ($nightEmployees as $employee) {
                        $exists = DB::table('shift_assignments')
                            ->where('user_id', $employee->id)
                            ->where('date', $dateStr)
                            ->exists();
                        
                        if (!$exists) {
                            DB::table('shift_assignments')->insert([
                                'user_id' => $employee->id,
                                'shift_id' => $nightShiftId,
                                'date' => $dateStr,
                                'status' => 'scheduled',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $assignedCount++;
                        }
                    }
                }

                // 4. Create sample leave requests with approved status
                $sampleLeaves = [
                    [
                        'user_id' => $employees->first()->id,
                        'start_date' => Carbon::now()->addDays(5)->toDateString(),
                        'end_date' => Carbon::now()->addDays(7)->toDateString(),
                        'status' => 'approved'
                    ],
                    [
                        'user_id' => $employees->skip(1)->first()->id ?? $employees->first()->id,
                        'start_date' => Carbon::now()->addDays(10)->toDateString(),
                        'end_date' => Carbon::now()->addDays(12)->toDateString(),
                        'status' => 'approved'
                    ],
                    [
                        'user_id' => $employees->skip(2)->first()->id ?? $employees->first()->id,
                        'start_date' => Carbon::now()->addDays(15)->toDateString(),
                        'end_date' => Carbon::now()->addDays(16)->toDateString(),
                        'status' => 'pending'
                    ],
                ];

                foreach ($sampleLeaves as $leave) {
                    $exists = DB::table('leave_requests')
                        ->where('user_id', $leave['user_id'])
                        ->where('start_date', $leave['start_date'])
                        ->exists();
                    
                    if (!$exists) {
                        $startDate = Carbon::parse($leave['start_date']);
                        $endDate = Carbon::parse($leave['end_date']);
                        $totalDays = $startDate->diffInDays($endDate) + 1;
                        
                        DB::table('leave_requests')->insert([
                            'user_id' => $leave['user_id'],
                            'leave_type' => 'annual',
                            'start_date' => $leave['start_date'],
                            'end_date' => $leave['end_date'],
                            'total_days' => $totalDays,
                            'reason' => 'Sample leave request for testing',
                            'status' => $leave['status'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                DB::statement("SET search_path TO public");
                $this->command->info("  ✓ Created 2 shifts");
                $this->command->info("  ✓ Assigned {$assignedCount} shift assignments for 30 days");
                $this->command->info("  ✓ Created 3 sample leave requests\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->command->error("  ✗ Error: {$e->getMessage()}\n");
            }
        }

        $this->command->info('Shift seeding completed!');
        $this->command->info('');
        $this->command->info('=== Shift Information ===');
        $this->command->info('Morning Shift: 07:00 - 15:00 (Orange)');
        $this->command->info('Night Shift: 15:00 - 23:00 (Blue)');
        $this->command->info('');
        $this->command->info('Employees are distributed evenly between shifts');
        $this->command->info('Shift assignments created for next 30 days');
    }
}
