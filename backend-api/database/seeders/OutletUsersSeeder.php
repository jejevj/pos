<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Outlet;

class OutletUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $outlets = Outlet::all();
        
        if ($outlets->isEmpty()) {
            $this->command->error('No outlets found');
            return;
        }

        foreach ($outlets as $outlet) {
            $this->command->info("Seeding users for outlet: {$outlet->nama}");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Get roles
                $ownerRole = DB::table('roles')->where('name', 'owner')->first();
                $spvRole = DB::table('roles')->where('name', 'spv')->first();
                $managerRole = DB::table('roles')->where('name', 'manager')->first();
                $cashierRole = DB::table('roles')->where('name', 'cashier')->first();
                $baristaRole = DB::table('roles')->where('name', 'barista')->first();
                $waitressRole = DB::table('roles')->where('name', 'waitress')->first();
                $kitchenRole = DB::table('roles')->where('name', 'kitchen_staff')->first();

                // Sample users with different roles
                $users = [
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Owner Demo',
                        'email' => 'owner@outlet.com',
                        'password' => Hash::make('password'),
                        'phone' => '081234567890',
                        'role_id' => $ownerRole?->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Supervisor Demo',
                        'email' => 'spv@outlet.com',
                        'password' => Hash::make('password'),
                        'phone' => '081234567891',
                        'role_id' => $spvRole?->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Manager Demo',
                        'email' => 'manager@outlet.com',
                        'password' => Hash::make('password'),
                        'phone' => '081234567892',
                        'role_id' => $managerRole?->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Kasir Satu',
                        'email' => 'kasir1@outlet.com',
                        'password' => Hash::make('password'),
                        'phone' => '081234567893',
                        'role_id' => $cashierRole?->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Kasir Dua',
                        'email' => 'kasir2@outlet.com',
                        'password' => Hash::make('password'),
                        'phone' => '081234567894',
                        'role_id' => $cashierRole?->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Barista Satu',
                        'email' => 'barista1@outlet.com',
                        'password' => Hash::make('password'),
                        'phone' => '081234567895',
                        'role_id' => $baristaRole?->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Barista Dua',
                        'email' => 'barista2@outlet.com',
                        'password' => Hash::make('password'),
                        'phone' => '081234567896',
                        'role_id' => $baristaRole?->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Waitress Satu',
                        'email' => 'waitress1@outlet.com',
                        'password' => Hash::make('password'),
                        'phone' => '081234567897',
                        'role_id' => $waitressRole?->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Waitress Dua',
                        'email' => 'waitress2@outlet.com',
                        'password' => Hash::make('password'),
                        'phone' => '081234567898',
                        'role_id' => $waitressRole?->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Kitchen Staff Satu',
                        'email' => 'kitchen1@outlet.com',
                        'password' => Hash::make('password'),
                        'phone' => '081234567899',
                        'role_id' => $kitchenRole?->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Kitchen Staff Dua',
                        'email' => 'kitchen2@outlet.com',
                        'password' => Hash::make('password'),
                        'phone' => '081234567800',
                        'role_id' => $kitchenRole?->id,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ];

                $insertedCount = 0;
                foreach ($users as $userData) {
                    // Check if user already exists
                    $exists = DB::table('outlet_users')
                        ->where('email', $userData['email'])
                        ->whereNull('deleted_at')
                        ->exists();
                    
                    if (!$exists) {
                        $userId = DB::table('outlet_users')->insertGetId($userData);
                        
                        // Assign role to user in user_roles table
                        if ($userData['role_id']) {
                            DB::table('user_roles')->insert([
                                'user_id' => $userId,
                                'role_id' => $userData['role_id'],
                                'created_at' => now(),
                            ]);
                        }
                        
                        $insertedCount++;
                    }
                }

                DB::statement("SET search_path TO public");
                $this->command->info("  ✓ Seeded {$insertedCount} users for {$outlet->nama}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->command->error("  ✗ Error: {$e->getMessage()}\n");
            }
        }

        $this->command->info('Outlet users seeding completed!');
        $this->command->info('');
        $this->command->info('=== Login Credentials ===');
        $this->command->info('All users have password: password');
        $this->command->info('');
        $this->command->info('Emails:');
        $this->command->info('  - owner@outlet.com (Owner)');
        $this->command->info('  - spv@outlet.com (Supervisor)');
        $this->command->info('  - manager@outlet.com (Manager)');
        $this->command->info('  - kasir1@outlet.com (Cashier 1)');
        $this->command->info('  - kasir2@outlet.com (Cashier 2)');
        $this->command->info('  - barista1@outlet.com (Barista 1)');
        $this->command->info('  - barista2@outlet.com (Barista 2)');
        $this->command->info('  - waitress1@outlet.com (Waitress 1)');
        $this->command->info('  - waitress2@outlet.com (Waitress 2)');
        $this->command->info('  - kitchen1@outlet.com (Kitchen Staff 1)');
        $this->command->info('  - kitchen2@outlet.com (Kitchen Staff 2)');
    }
}
