<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class SeedRolesPermissions extends Command
{
    protected $signature = 'outlets:seed-rbac {--outlet-id=}';
    protected $description = 'Seed default roles and permissions for outlets';

    public function handle()
    {
        $outletId = $this->option('outlet-id');
        
        if ($outletId) {
            $outlets = Outlet::where('id', $outletId)->get();
        } else {
            $outlets = Outlet::all();
        }
        
        if ($outlets->isEmpty()) {
            $this->error('No outlets found');
            return 1;
        }

        foreach ($outlets as $outlet) {
            $this->info("Seeding RBAC for outlet: {$outlet->name} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Seed Permissions
                $this->seedPermissions();
                
                // Seed Roles
                $this->seedRoles();
                
                DB::statement("SET search_path TO public");
                $this->info("  ✓ Completed for {$outlet->name}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->error("  ✗ Error: {$e->getMessage()}\n");
            }
        }

        $this->info('RBAC seeding completed!');
        return 0;
    }

    private function seedPermissions()
    {
        $permissions = [
            // Dashboard
            ['name' => 'view_dashboard', 'display_name' => 'View Dashboard', 'group_name' => 'Dashboard'],
            
            // Inventory Management
            ['name' => 'view_inventory', 'display_name' => 'View Inventory', 'group_name' => 'Inventory'],
            ['name' => 'create_inventory', 'display_name' => 'Create Inventory', 'group_name' => 'Inventory'],
            ['name' => 'edit_inventory', 'display_name' => 'Edit Inventory', 'group_name' => 'Inventory'],
            ['name' => 'delete_inventory', 'display_name' => 'Delete Inventory', 'group_name' => 'Inventory'],
            
            // Menu Management
            ['name' => 'view_menu', 'display_name' => 'View Menu', 'group_name' => 'Menu'],
            ['name' => 'create_menu', 'display_name' => 'Create Menu', 'group_name' => 'Menu'],
            ['name' => 'edit_menu', 'display_name' => 'Edit Menu', 'group_name' => 'Menu'],
            ['name' => 'delete_menu', 'display_name' => 'Delete Menu', 'group_name' => 'Menu'],
            
            // POS
            ['name' => 'access_pos', 'display_name' => 'Access POS', 'group_name' => 'POS'],
            ['name' => 'create_order', 'display_name' => 'Create Order', 'group_name' => 'POS'],
            ['name' => 'cancel_order', 'display_name' => 'Cancel Order', 'group_name' => 'POS'],
            ['name' => 'apply_discount', 'display_name' => 'Apply Discount', 'group_name' => 'POS'],
            
            // Transactions
            ['name' => 'view_transactions', 'display_name' => 'View Transactions', 'group_name' => 'Transactions'],
            ['name' => 'refund_transaction', 'display_name' => 'Refund Transaction', 'group_name' => 'Transactions'],
            
            // Reports
            ['name' => 'view_reports', 'display_name' => 'View Reports', 'group_name' => 'Reports'],
            ['name' => 'export_reports', 'display_name' => 'Export Reports', 'group_name' => 'Reports'],
            
            // HR Management
            ['name' => 'view_employees', 'display_name' => 'View Employees', 'group_name' => 'HR'],
            ['name' => 'create_employee', 'display_name' => 'Create Employee', 'group_name' => 'HR'],
            ['name' => 'edit_employee', 'display_name' => 'Edit Employee', 'group_name' => 'HR'],
            ['name' => 'delete_employee', 'display_name' => 'Delete Employee', 'group_name' => 'HR'],
            ['name' => 'view_attendance', 'display_name' => 'View Attendance', 'group_name' => 'HR'],
            ['name' => 'manage_payroll', 'display_name' => 'Manage Payroll', 'group_name' => 'HR'],
            ['name' => 'manage_kasbon', 'display_name' => 'Manage Kasbon', 'group_name' => 'HR'],
            ['name' => 'approve_leave', 'display_name' => 'Approve Leave', 'group_name' => 'HR'],
            
            // Purchases & Expenses
            ['name' => 'view_purchases', 'display_name' => 'View Purchases', 'group_name' => 'Finance'],
            ['name' => 'create_purchase', 'display_name' => 'Create Purchase', 'group_name' => 'Finance'],
            ['name' => 'view_expenses', 'display_name' => 'View Expenses', 'group_name' => 'Finance'],
            ['name' => 'create_expense', 'display_name' => 'Create Expense', 'group_name' => 'Finance'],
            
            // Members
            ['name' => 'view_members', 'display_name' => 'View Members', 'group_name' => 'Members'],
            ['name' => 'create_member', 'display_name' => 'Create Member', 'group_name' => 'Members'],
            ['name' => 'edit_member', 'display_name' => 'Edit Member', 'group_name' => 'Members'],
            
            // Promos
            ['name' => 'view_promos', 'display_name' => 'View Promos', 'group_name' => 'Marketing'],
            ['name' => 'create_promo', 'display_name' => 'Create Promo', 'group_name' => 'Marketing'],
            ['name' => 'edit_promo', 'display_name' => 'Edit Promo', 'group_name' => 'Marketing'],
            ['name' => 'delete_promo', 'display_name' => 'Delete Promo', 'group_name' => 'Marketing'],
            
            // Settings
            ['name' => 'view_settings', 'display_name' => 'View Settings', 'group_name' => 'Settings'],
            ['name' => 'edit_settings', 'display_name' => 'Edit Settings', 'group_name' => 'Settings'],
            ['name' => 'manage_roles', 'display_name' => 'Manage Roles & Permissions', 'group_name' => 'Settings'],
            ['name' => 'manage_users', 'display_name' => 'Manage Users', 'group_name' => 'Settings'],
            
            // Stock Opname
            ['name' => 'view_stock_opname', 'display_name' => 'View Stock Opname', 'group_name' => 'Inventory'],
            ['name' => 'create_stock_opname', 'display_name' => 'Create Stock Opname', 'group_name' => 'Inventory'],
            
            // Tables
            ['name' => 'manage_tables', 'display_name' => 'Manage Tables', 'group_name' => 'Settings'],
            
            // Kitchen Display
            ['name' => 'access_kitchen_display', 'display_name' => 'Access Kitchen Display', 'group_name' => 'Kitchen'],
        ];

        foreach ($permissions as $permission) {
            $exists = DB::table('permissions')->where('name', $permission['name'])->exists();
            if (!$exists) {
                DB::table('permissions')->insert(array_merge($permission, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
                $this->info("  ✓ Created permission: {$permission['name']}");
            }
        }
    }

    private function seedRoles()
    {
        // Get all permission IDs
        $allPermissions = DB::table('permissions')->pluck('id')->toArray();
        
        // Define roles with their permissions
        $roles = [
            [
                'name' => 'owner',
                'display_name' => 'Owner',
                'description' => 'Full access to all features',
                'level' => 100,
                'permissions' => $allPermissions // Owner has all permissions
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Administrative access with most permissions',
                'level' => 90,
                'permissions' => $this->getPermissionsByNames([
                    'view_dashboard', 'view_inventory', 'create_inventory', 'edit_inventory',
                    'view_menu', 'create_menu', 'edit_menu', 'access_pos', 'create_order',
                    'cancel_order', 'apply_discount', 'view_transactions', 'view_reports',
                    'export_reports', 'view_employees', 'create_employee', 'edit_employee',
                    'view_attendance', 'manage_payroll', 'manage_kasbon', 'approve_leave',
                    'view_purchases', 'create_purchase', 'view_expenses', 'create_expense',
                    'view_members', 'create_member', 'edit_member', 'view_promos',
                    'create_promo', 'edit_promo', 'view_settings', 'edit_settings',
                    'manage_users', 'view_stock_opname', 'create_stock_opname',
                    'manage_tables'
                ])
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Manage daily operations',
                'level' => 70,
                'permissions' => $this->getPermissionsByNames([
                    'view_dashboard', 'view_inventory', 'create_inventory', 'edit_inventory',
                    'view_menu', 'access_pos', 'create_order', 'cancel_order',
                    'apply_discount', 'view_transactions', 'view_reports',
                    'view_employees', 'view_attendance', 'approve_leave',
                    'view_purchases', 'create_purchase', 'view_expenses', 'create_expense',
                    'view_members', 'create_member', 'view_promos', 'view_stock_opname',
                    'create_stock_opname'
                ])
            ],
            [
                'name' => 'cashier',
                'display_name' => 'Cashier',
                'description' => 'Handle POS and transactions',
                'level' => 50,
                'permissions' => $this->getPermissionsByNames([
                    'view_dashboard', 'access_pos', 'create_order', 'view_transactions',
                    'view_members', 'create_member', 'view_menu'
                ])
            ],
            [
                'name' => 'kitchen_staff',
                'display_name' => 'Kitchen Staff',
                'description' => 'Access kitchen display',
                'level' => 40,
                'permissions' => $this->getPermissionsByNames([
                    'access_kitchen_display', 'view_menu'
                ])
            ],
            [
                'name' => 'staff',
                'display_name' => 'Staff',
                'description' => 'Basic staff access',
                'level' => 30,
                'permissions' => $this->getPermissionsByNames([
                    'view_dashboard', 'view_menu'
                ])
            ]
        ];

        foreach ($roles as $roleData) {
            $exists = DB::table('roles')->where('name', $roleData['name'])->exists();
            
            if (!$exists) {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => $roleData['name'],
                    'display_name' => $roleData['display_name'],
                    'description' => $roleData['description'],
                    'level' => $roleData['level'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Assign permissions to role
                foreach ($roleData['permissions'] as $permissionId) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => now()
                    ]);
                }

                $this->info("  ✓ Created role: {$roleData['name']} with " . count($roleData['permissions']) . " permissions");
            } else {
                $this->warn("  - Role {$roleData['name']} already exists");
            }
        }
    }

    private function getPermissionsByNames($names)
    {
        return DB::table('permissions')
            ->whereIn('name', $names)
            ->pluck('id')
            ->toArray();
    }
}
