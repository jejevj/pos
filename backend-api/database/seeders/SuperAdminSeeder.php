<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if superadmin already exists
        $superadmin = User::where('email', 'admin@saasapp.com')->first();

        if (!$superadmin) {
            $superadmin = User::create([
                'name' => 'Super Admin',
                'email' => 'admin@saasapp.com',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'email_verified_at' => now(),
            ]);

            $this->command->info('✅ Super Admin created successfully!');
            $this->command->info('📧 Email: admin@saasapp.com');
            $this->command->info('🔑 Password: password');
            $this->command->warn('⚠️  Please change the password after first login!');
        } else {
            $this->command->info('ℹ️  Super Admin already exists.');
        }

        // Optional: Create additional admin users
        $admin = User::where('email', 'admin2@saasapp.com')->first();
        
        if (!$admin) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin2@saasapp.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);

            $this->command->info('✅ Admin User created successfully!');
            $this->command->info('📧 Email: admin2@saasapp.com');
            $this->command->info('🔑 Password: password');
        }

        // Optional: Create test regular user
        $user = User::where('email', 'user@saasapp.com')->first();
        
        if (!$user) {
            User::create([
                'name' => 'Regular User',
                'email' => 'user@saasapp.com',
                'password' => Hash::make('password'),
                'role' => 'user',
                'email_verified_at' => now(),
            ]);

            $this->command->info('✅ Regular User created successfully!');
            $this->command->info('📧 Email: user@saasapp.com');
            $this->command->info('🔑 Password: password');
        }
    }
}
