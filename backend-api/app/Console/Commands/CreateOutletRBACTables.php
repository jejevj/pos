<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class CreateOutletRBACTables extends Command
{
    protected $signature = 'outlets:create-rbac-tables';
    protected $description = 'Create RBAC tables (roles, permissions) in all outlet schemas';

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
                
                // Create roles table
                $rolesExists = DB::select("SELECT to_regclass('{$outlet->schema_name}.roles')");
                if (!$rolesExists || $rolesExists[0]->to_regclass === null) {
                    DB::statement("
                        CREATE TABLE {$outlet->schema_name}.roles (
                            id SERIAL PRIMARY KEY,
                            name VARCHAR(50) UNIQUE NOT NULL,
                            display_name VARCHAR(100) NOT NULL,
                            description TEXT,
                            level INTEGER DEFAULT 0,
                            is_active BOOLEAN DEFAULT TRUE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $this->info("  ✓ Created roles table");
                } else {
                    $this->warn("  - roles table already exists");
                }

                // Create permissions table
                $permissionsExists = DB::select("SELECT to_regclass('{$outlet->schema_name}.permissions')");
                if (!$permissionsExists || $permissionsExists[0]->to_regclass === null) {
                    DB::statement("
                        CREATE TABLE {$outlet->schema_name}.permissions (
                            id SERIAL PRIMARY KEY,
                            name VARCHAR(100) UNIQUE NOT NULL,
                            display_name VARCHAR(150) NOT NULL,
                            group_name VARCHAR(50) NOT NULL,
                            description TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $this->info("  ✓ Created permissions table");
                } else {
                    $this->warn("  - permissions table already exists");
                }

                // Create role_permissions pivot table
                $rolePermissionsExists = DB::select("SELECT to_regclass('{$outlet->schema_name}.role_permissions')");
                if (!$rolePermissionsExists || $rolePermissionsExists[0]->to_regclass === null) {
                    DB::statement("
                        CREATE TABLE {$outlet->schema_name}.role_permissions (
                            id SERIAL PRIMARY KEY,
                            role_id INTEGER NOT NULL,
                            permission_id INTEGER NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (role_id) REFERENCES {$outlet->schema_name}.roles(id) ON DELETE CASCADE,
                            FOREIGN KEY (permission_id) REFERENCES {$outlet->schema_name}.permissions(id) ON DELETE CASCADE,
                            UNIQUE(role_id, permission_id)
                        )
                    ");
                    $this->info("  ✓ Created role_permissions table");
                } else {
                    $this->warn("  - role_permissions table already exists");
                }

                // Create user_roles pivot table
                $userRolesExists = DB::select("SELECT to_regclass('{$outlet->schema_name}.user_roles')");
                if (!$userRolesExists || $userRolesExists[0]->to_regclass === null) {
                    DB::statement("
                        CREATE TABLE {$outlet->schema_name}.user_roles (
                            id SERIAL PRIMARY KEY,
                            user_id INTEGER NOT NULL,
                            role_id INTEGER NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (user_id) REFERENCES {$outlet->schema_name}.outlet_users(id) ON DELETE CASCADE,
                            FOREIGN KEY (role_id) REFERENCES {$outlet->schema_name}.roles(id) ON DELETE CASCADE,
                            UNIQUE(user_id, role_id)
                        )
                    ");
                    $this->info("  ✓ Created user_roles table");
                } else {
                    $this->warn("  - user_roles table already exists");
                }

                // Add role_id column to outlet_users if not exists
                $userColumns = DB::select("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_schema = '{$outlet->schema_name}' 
                    AND table_name = 'outlet_users'
                ");
                
                $hasRoleId = collect($userColumns)->contains('column_name', 'role_id');
                
                if (!$hasRoleId) {
                    DB::statement("ALTER TABLE {$outlet->schema_name}.outlet_users ADD COLUMN role_id INTEGER");
                    DB::statement("ALTER TABLE {$outlet->schema_name}.outlet_users ADD CONSTRAINT fk_outlet_users_role FOREIGN KEY (role_id) REFERENCES {$outlet->schema_name}.roles(id) ON DELETE SET NULL");
                    $this->info("  ✓ Added role_id to outlet_users table");
                }

                DB::statement("SET search_path TO public");
                $this->info("  ✓ Completed for {$outlet->nama}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->error("  ✗ Error: {$e->getMessage()}\n");
            }
        }

        $this->info('RBAC tables creation completed!');
        return 0;
    }
}
