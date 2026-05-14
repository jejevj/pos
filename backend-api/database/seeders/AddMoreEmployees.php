<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Outlet;

class AddMoreEmployees extends Seeder
{
    public function run(): void
    {
        $outlets = Outlet::all();
        
        if ($outlets->isEmpty()) {
            $this->command->error('No outlets found');
            return;
        }

        foreach ($outlets as $outlet) {
            $this->command->info("Adding employees for outlet: {$outlet->nama}");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Get barista role
                $baristaRole = DB::table('roles')->where('name', 'barista')->first();
                
                if (!$baristaRole) {
                    $this->command->warn('  ⚠ Barista role not found');
                    DB::statement("SET search_path TO public");
                    continue;
                }
                
                // Add 8 more baristas (total 10)
                $newBaristas = [
                    ['name' => 'Barista Tiga', 'email' => 'barista3@outlet.com'],
                    ['name' => 'Barista Empat', 'email' => 'barista4@outlet.com'],
                    ['name' => 'Barista Lima', 'email' => 'barista5@outlet.com'],
                    ['name' => 'Barista Enam', 'email' => 'barista6@outlet.com'],
                    ['name' => 'Barista Tujuh', 'email' => 'barista7@outlet.com'],
                    ['name' => 'Barista Delapan', 'email' => 'barista8@outlet.com'],
                    ['name' => 'Barista Sembilan', 'email' => 'barista9@outlet.com'],
                    ['name' => 'Barista Sepuluh', 'email' => 'barista10@outlet.com'],
                ];
                
                foreach ($newBaristas as $barista) {
                    // Check if already exists
                    $exists = DB::table('outlet_users')->where('email', $barista['email'])->exists();
                    
                    if ($exists) {
                        $this->command->info("  ✓ {$barista['name']} already exists");
                        continue;
                    }
                    
                    // Create user
                    $userId = DB::table('outlet_users')->insertGetId([
                        'outlet_id' => $outlet->id,
                        'name' => $barista['name'],
                        'email' => $barista['email'],
                        'password' => Hash::make('password'),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Assign role
                    DB::table('user_roles')->insert([
                        'user_id' => $userId,
                        'role_id' => $baristaRole->id,
                    ]);
                    
                    // Create employee info
                    DB::table('employee_info')->insert([
                        'user_id' => $userId,
                        'join_date' => now(),
                        'employment_type' => 'full_time',
                        'basic_salary' => 3500000,
                        'overtime_rate' => 20000,
                        'day_off' => 0, // Will be set by auto-schedule
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    $this->command->info("  ✓ Created {$barista['name']}");
                }
                
                DB::statement("SET search_path TO public");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->command->error("  ✗ Error: {$e->getMessage()}");
            }
        }

        $this->command->info('');
        $this->command->info('Employee addition completed!');
        $this->command->info('Total Baristas: 10');
    }
}
