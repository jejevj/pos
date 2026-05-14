<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class CreateMembershipTables extends Command
{
    protected $signature = 'outlets:create-membership-tables';
    protected $description = 'Create membership tables in all outlet schemas';

    public function handle()
    {
        $outlets = Outlet::all();
        
        if ($outlets->isEmpty()) {
            $this->error('No outlets found');
            return 1;
        }

        foreach ($outlets as $outlet) {
            $this->info("Processing outlet: {$outlet->nama} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Create members table
                $membersExists = DB::select("SELECT to_regclass('{$outlet->schema_name}.members')");
                if (!$membersExists || $membersExists[0]->to_regclass === null) {
                    DB::statement("
                        CREATE TABLE {$outlet->schema_name}.members (
                            id SERIAL PRIMARY KEY,
                            card_number VARCHAR(50) UNIQUE NOT NULL,
                            nama VARCHAR(100) NOT NULL,
                            phone VARCHAR(50),
                            email VARCHAR(100),
                            password VARCHAR(255),
                            tanggal_lahir DATE,
                            jenis_kelamin VARCHAR(20),
                            alamat TEXT,
                            points INTEGER DEFAULT 0,
                            tier VARCHAR(50) DEFAULT 'Silver',
                            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            last_transaction_at TIMESTAMP,
                            is_active BOOLEAN DEFAULT TRUE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            deleted_at TIMESTAMP
                        )
                    ");
                    $this->info("  ✓ Created members table");
                } else {
                    $this->warn("  - members table already exists");
                }

                // Create membership_settings table
                $settingsExists = DB::select("SELECT to_regclass('{$outlet->schema_name}.membership_settings')");
                if (!$settingsExists || $settingsExists[0]->to_regclass === null) {
                    DB::statement("
                        CREATE TABLE {$outlet->schema_name}.membership_settings (
                            id SERIAL PRIMARY KEY,
                            point_conversion_rate INTEGER DEFAULT 1000,
                            point_per_rupiah DECIMAL(10,2) DEFAULT 1.00,
                            point_expiry_days INTEGER,
                            min_transaction_for_points DECIMAL(15,2) DEFAULT 0,
                            tiers JSONB DEFAULT '[
                                {\"name\": \"Silver\", \"min_points\": 0, \"discount_percentage\": 0},
                                {\"name\": \"Gold\", \"min_points\": 1000, \"discount_percentage\": 5},
                                {\"name\": \"Platinum\", \"min_points\": 5000, \"discount_percentage\": 10}
                            ]'::jsonb,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    
                    // Insert default settings
                    DB::statement("
                        INSERT INTO {$outlet->schema_name}.membership_settings 
                        (point_conversion_rate, point_per_rupiah, min_transaction_for_points) 
                        VALUES (1000, 1.00, 0)
                    ");
                    
                    $this->info("  ✓ Created membership_settings table");
                } else {
                    $this->warn("  - membership_settings table already exists");
                }

                // Create point_transactions table
                $transactionsExists = DB::select("SELECT to_regclass('{$outlet->schema_name}.point_transactions')");
                if (!$transactionsExists || $transactionsExists[0]->to_regclass === null) {
                    DB::statement("
                        CREATE TABLE {$outlet->schema_name}.point_transactions (
                            id SERIAL PRIMARY KEY,
                            member_id INTEGER NOT NULL,
                            type VARCHAR(20) NOT NULL,
                            amount INTEGER NOT NULL,
                            description TEXT,
                            order_id INTEGER,
                            balance_before INTEGER DEFAULT 0,
                            balance_after INTEGER DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (member_id) REFERENCES {$outlet->schema_name}.members(id) ON DELETE CASCADE
                        )
                    ");
                    $this->info("  ✓ Created point_transactions table");
                } else {
                    $this->warn("  - point_transactions table already exists");
                }

                // Add password column to existing members table if not exists
                $memberColumns = DB::select("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_schema = '{$outlet->schema_name}' 
                    AND table_name = 'members'
                ");
                
                $hasPassword = collect($memberColumns)->contains('column_name', 'password');
                
                if (!$hasPassword) {
                    DB::statement("ALTER TABLE {$outlet->schema_name}.members ADD COLUMN password VARCHAR(255)");
                    $this->info("  ✓ Added password column to members table");
                }

                // Add member_id to orders table if not exists
                $orderColumns = DB::select("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_schema = '{$outlet->schema_name}' 
                    AND table_name = 'orders'
                ");
                
                $hasMemberId = collect($orderColumns)->contains('column_name', 'member_id');
                $hasPointsEarned = collect($orderColumns)->contains('column_name', 'points_earned');
                $hasPointsRedeemed = collect($orderColumns)->contains('column_name', 'points_redeemed');
                
                if (!$hasMemberId) {
                    DB::statement("ALTER TABLE {$outlet->schema_name}.orders ADD COLUMN member_id INTEGER");
                    
                    // Check if members table exists before adding foreign key
                    $membersTableExists = DB::select("SELECT to_regclass('{$outlet->schema_name}.members')");
                    if ($membersTableExists && $membersTableExists[0]->to_regclass !== null) {
                        DB::statement("ALTER TABLE {$outlet->schema_name}.orders ADD CONSTRAINT fk_orders_member FOREIGN KEY (member_id) REFERENCES {$outlet->schema_name}.members(id) ON DELETE SET NULL");
                    }
                    
                    $this->info("  ✓ Added member_id to orders table");
                }
                
                if (!$hasPointsEarned) {
                    DB::statement("ALTER TABLE {$outlet->schema_name}.orders ADD COLUMN points_earned INTEGER DEFAULT 0");
                    $this->info("  ✓ Added points_earned to orders table");
                }
                
                if (!$hasPointsRedeemed) {
                    DB::statement("ALTER TABLE {$outlet->schema_name}.orders ADD COLUMN points_redeemed INTEGER DEFAULT 0");
                    $this->info("  ✓ Added points_redeemed to orders table");
                }

                // Add is_member_only to promos table if not exists
                $promoColumns = DB::select("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_schema = '{$outlet->schema_name}' 
                    AND table_name = 'promos'
                ");
                
                $hasMemberOnly = collect($promoColumns)->contains('column_name', 'is_member_only');
                
                if (!$hasMemberOnly) {
                    DB::statement("ALTER TABLE {$outlet->schema_name}.promos ADD COLUMN is_member_only BOOLEAN DEFAULT FALSE");
                    $this->info("  ✓ Added is_member_only to promos table");
                }

                // Seed sample members
                $memberCount = DB::select("SELECT COUNT(*) as count FROM {$outlet->schema_name}.members")[0]->count;
                
                if ($memberCount == 0) {
                    DB::statement("
                        INSERT INTO {$outlet->schema_name}.members 
                        (card_number, nama, phone, email, points, tier, joined_at) 
                        VALUES 
                        ('MBR001', 'Budi Santoso', '081234567890', 'budi@example.com', 500, 'Silver', CURRENT_TIMESTAMP),
                        ('MBR002', 'Siti Nurhaliza', '081234567891', 'siti@example.com', 1500, 'Gold', CURRENT_TIMESTAMP),
                        ('MBR003', 'Ahmad Wijaya', '081234567892', 'ahmad@example.com', 6000, 'Platinum', CURRENT_TIMESTAMP)
                    ");
                    $this->info("  ✓ Seeded 3 sample members");
                }

                DB::statement("SET search_path TO public");
                $this->info("  ✓ Completed for {$outlet->nama}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->error("  ✗ Error: {$e->getMessage()}\n");
            }
        }

        $this->info('Membership tables creation completed!');
        return 0;
    }
}
