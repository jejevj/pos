<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

class OutletRBACSeeder extends Seeder
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
            $this->command->info("Seeding RBAC for outlet: {$outlet->nama}");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Define roles with hierarchy level (higher = more power)
                $roles = [
                    ['name' => 'owner', 'display_name' => 'Pemilik Outlet', 'description' => 'Full access to all outlet features', 'level' => 100],
                    ['name' => 'spv', 'display_name' => 'Supervisor', 'description' => 'High-level management access', 'level' => 90],
                    ['name' => 'manager', 'display_name' => 'Manager', 'description' => 'Operational management access', 'level' => 80],
                    ['name' => 'cashier', 'display_name' => 'Kasir', 'description' => 'Transaction and POS access', 'level' => 50],
                    ['name' => 'barista', 'display_name' => 'Barista', 'description' => 'Bar operations access', 'level' => 40],
                    ['name' => 'waitress', 'display_name' => 'Waitress', 'description' => 'Service and table management', 'level' => 30],
                    ['name' => 'kitchen_staff', 'display_name' => 'Kitchen Staff', 'description' => 'Kitchen display access only', 'level' => 20],
                ];

                foreach ($roles as $role) {
                    DB::table('roles')->updateOrInsert(
                        ['name' => $role['name']],
                        $role + ['created_at' => now(), 'updated_at' => now()]
                    );
                }

                $this->command->info("  ✓ Seeded roles");

                // Define permissions grouped by module
                $permissions = [
                    // Dashboard & Reports
                    ['name' => 'view_dashboard', 'display_name' => 'Lihat Dashboard', 'group_name' => 'dashboard'],
                    ['name' => 'view_reports', 'display_name' => 'Lihat Laporan', 'group_name' => 'reports'],
                    ['name' => 'view_analytics', 'display_name' => 'Lihat Analytics', 'group_name' => 'reports'],
                    ['name' => 'export_reports', 'display_name' => 'Export Laporan', 'group_name' => 'reports'],
                    
                    // User Management
                    ['name' => 'view_users', 'display_name' => 'Lihat User', 'group_name' => 'users'],
                    ['name' => 'create_users', 'display_name' => 'Tambah User', 'group_name' => 'users'],
                    ['name' => 'edit_users', 'display_name' => 'Edit User', 'group_name' => 'users'],
                    ['name' => 'delete_users', 'display_name' => 'Hapus User', 'group_name' => 'users'],
                    ['name' => 'manage_roles', 'display_name' => 'Kelola Role', 'group_name' => 'users'],
                    
                    // Menu Management
                    ['name' => 'view_menu', 'display_name' => 'Lihat Menu', 'group_name' => 'menu'],
                    ['name' => 'create_menu', 'display_name' => 'Tambah Menu', 'group_name' => 'menu'],
                    ['name' => 'edit_menu', 'display_name' => 'Edit Menu', 'group_name' => 'menu'],
                    ['name' => 'delete_menu', 'display_name' => 'Hapus Menu', 'group_name' => 'menu'],
                    ['name' => 'edit_menu_price', 'display_name' => 'Edit Harga Menu', 'group_name' => 'menu'],
                    ['name' => 'view_menu_cost', 'display_name' => 'Lihat Harga Modal', 'group_name' => 'menu'],
                    
                    // Inventory Management
                    ['name' => 'view_inventory', 'display_name' => 'Lihat Inventory', 'group_name' => 'inventory'],
                    ['name' => 'create_inventory', 'display_name' => 'Tambah Bahan Baku', 'group_name' => 'inventory'],
                    ['name' => 'edit_inventory', 'display_name' => 'Edit Bahan Baku', 'group_name' => 'inventory'],
                    ['name' => 'delete_inventory', 'display_name' => 'Hapus Bahan Baku', 'group_name' => 'inventory'],
                    ['name' => 'manage_stock_opname', 'display_name' => 'Kelola Stock Opname', 'group_name' => 'inventory'],
                    ['name' => 'manage_suppliers', 'display_name' => 'Kelola Supplier', 'group_name' => 'inventory'],
                    
                    // POS & Transactions
                    ['name' => 'access_pos', 'display_name' => 'Akses POS', 'group_name' => 'pos'],
                    ['name' => 'access_pos_bar', 'display_name' => 'Akses POS Bar', 'group_name' => 'pos'],
                    ['name' => 'create_order', 'display_name' => 'Buat Order', 'group_name' => 'pos'],
                    ['name' => 'view_transactions', 'display_name' => 'Lihat Transaksi', 'group_name' => 'pos'],
                    ['name' => 'void_order', 'display_name' => 'Void Order', 'group_name' => 'pos'],
                    ['name' => 'approve_void', 'display_name' => 'Approve Void', 'group_name' => 'pos'],
                    ['name' => 'refund_order', 'display_name' => 'Refund Order', 'group_name' => 'pos'],
                    ['name' => 'approve_refund', 'display_name' => 'Approve Refund', 'group_name' => 'pos'],
                    
                    // Kitchen Display System
                    ['name' => 'access_kds', 'display_name' => 'Akses Kitchen Display', 'group_name' => 'kds'],
                    ['name' => 'update_order_status', 'display_name' => 'Update Status Order', 'group_name' => 'kds'],
                    
                    // Table Management
                    ['name' => 'view_tables', 'display_name' => 'Lihat Meja', 'group_name' => 'tables'],
                    ['name' => 'manage_tables', 'display_name' => 'Kelola Meja', 'group_name' => 'tables'],
                    
                    // Membership
                    ['name' => 'view_members', 'display_name' => 'Lihat Member', 'group_name' => 'membership'],
                    ['name' => 'create_members', 'display_name' => 'Tambah Member', 'group_name' => 'membership'],
                    ['name' => 'edit_members', 'display_name' => 'Edit Member', 'group_name' => 'membership'],
                    ['name' => 'delete_members', 'display_name' => 'Hapus Member', 'group_name' => 'membership'],
                    ['name' => 'adjust_points', 'display_name' => 'Sesuaikan Poin', 'group_name' => 'membership'],
                    ['name' => 'manage_membership_settings', 'display_name' => 'Kelola Pengaturan Membership', 'group_name' => 'membership'],
                    
                    // Promo Management
                    ['name' => 'view_promos', 'display_name' => 'Lihat Promo', 'group_name' => 'promo'],
                    ['name' => 'create_promos', 'display_name' => 'Tambah Promo', 'group_name' => 'promo'],
                    ['name' => 'edit_promos', 'display_name' => 'Edit Promo', 'group_name' => 'promo'],
                    ['name' => 'delete_promos', 'display_name' => 'Hapus Promo', 'group_name' => 'promo'],
                    
                    // Settings
                    ['name' => 'view_settings', 'display_name' => 'Lihat Pengaturan', 'group_name' => 'settings'],
                    ['name' => 'edit_settings', 'display_name' => 'Edit Pengaturan', 'group_name' => 'settings'],
                    ['name' => 'manage_stations', 'display_name' => 'Kelola Station', 'group_name' => 'settings'],
                    ['name' => 'manage_payment_methods', 'display_name' => 'Kelola Metode Pembayaran', 'group_name' => 'settings'],
                ];

                foreach ($permissions as $permission) {
                    DB::table('permissions')->updateOrInsert(
                        ['name' => $permission['name']],
                        $permission + ['created_at' => now(), 'updated_at' => now()]
                    );
                }

                $this->command->info("  ✓ Seeded permissions");

                // Assign permissions to roles
                $rolePermissions = [
                    'owner' => [
                        // Full access - all permissions
                        'view_dashboard', 'view_reports', 'view_analytics', 'export_reports',
                        'view_users', 'create_users', 'edit_users', 'delete_users', 'manage_roles',
                        'view_menu', 'create_menu', 'edit_menu', 'delete_menu', 'edit_menu_price', 'view_menu_cost',
                        'view_inventory', 'create_inventory', 'edit_inventory', 'delete_inventory', 'manage_stock_opname', 'manage_suppliers',
                        'access_pos', 'access_pos_bar', 'create_order', 'view_transactions', 'void_order', 'approve_void', 'refund_order', 'approve_refund',
                        'access_kds', 'update_order_status',
                        'view_tables', 'manage_tables',
                        'view_members', 'create_members', 'edit_members', 'delete_members', 'adjust_points', 'manage_membership_settings',
                        'view_promos', 'create_promos', 'edit_promos', 'delete_promos',
                        'view_settings', 'edit_settings', 'manage_stations', 'manage_payment_methods',
                    ],
                    'spv' => [
                        // High-level management
                        'view_dashboard', 'view_reports', 'view_analytics', 'export_reports',
                        'view_users', 'create_users', 'edit_users', 'delete_users',
                        'view_menu', 'create_menu', 'edit_menu', 'delete_menu', 'edit_menu_price', 'view_menu_cost',
                        'view_inventory', 'create_inventory', 'edit_inventory', 'delete_inventory', 'manage_stock_opname', 'manage_suppliers',
                        'access_pos', 'access_pos_bar', 'create_order', 'view_transactions', 'void_order', 'approve_void', 'refund_order', 'approve_refund',
                        'access_kds', 'update_order_status',
                        'view_tables', 'manage_tables',
                        'view_members', 'create_members', 'edit_members', 'delete_members', 'adjust_points', 'manage_membership_settings',
                        'view_promos', 'create_promos', 'edit_promos', 'delete_promos',
                        'view_settings', 'manage_stations', 'manage_payment_methods',
                    ],
                    'manager' => [
                        // Operational management
                        'view_dashboard', 'view_reports', 'export_reports',
                        'view_menu', 'create_menu', 'edit_menu', 'view_menu_cost',
                        'view_inventory', 'create_inventory', 'edit_inventory', 'manage_stock_opname', 'manage_suppliers',
                        'access_pos', 'access_pos_bar', 'create_order', 'view_transactions', 'void_order', 'approve_void',
                        'access_kds', 'update_order_status',
                        'view_tables', 'manage_tables',
                        'view_members', 'create_members', 'edit_members', 'adjust_points',
                        'view_promos',
                        'view_settings', 'manage_stations',
                    ],
                    'cashier' => [
                        // Transaction handling
                        'view_dashboard',
                        'view_menu',
                        'access_pos', 'access_pos_bar', 'create_order', 'view_transactions', 'void_order',
                        'view_tables',
                        'view_members',
                    ],
                    'barista' => [
                        // Bar operations
                        'view_menu',
                        'access_pos_bar', 'create_order',
                        'access_kds', 'update_order_status',
                    ],
                    'waitress' => [
                        // Service operations
                        'view_menu',
                        'access_pos', 'create_order',
                        'view_tables',
                    ],
                    'kitchen_staff' => [
                        // Kitchen only
                        'access_kds', 'update_order_status',
                    ],
                ];

                foreach ($rolePermissions as $roleName => $permissionNames) {
                    $role = DB::table('roles')->where('name', $roleName)->first();
                    
                    if ($role) {
                        foreach ($permissionNames as $permissionName) {
                            $permission = DB::table('permissions')->where('name', $permissionName)->first();
                            
                            if ($permission) {
                                DB::table('role_permissions')->updateOrInsert(
                                    ['role_id' => $role->id, 'permission_id' => $permission->id],
                                    ['created_at' => now()]
                                );
                            }
                        }
                    }
                }

                $this->command->info("  ✓ Assigned permissions to roles");

                DB::statement("SET search_path TO public");
                $this->command->info("  ✓ Completed for {$outlet->nama}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->command->error("  ✗ Error: {$e->getMessage()}\n");
            }
        }

        $this->command->info('RBAC seeding completed!');
    }
}
