<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RBACSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Starting RBAC Seeder...');

        // Create Permissions
        $this->createPermissions();

        // Create Roles
        $roles = $this->createRoles();

        // Create Menus
        $this->createMenus();

        // Create Users
        $this->createUsers($roles);

        $this->command->info('✅ RBAC Seeder completed successfully!');
    }

    private function createPermissions()
    {
        $this->command->info('📝 Creating permissions...');

        $permissions = [
            // User Management
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'users'],
            ['name' => 'users.edit', 'display_name' => 'Edit Users', 'group' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'group' => 'users'],

            // Role Management
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'group' => 'roles'],
            ['name' => 'roles.edit', 'display_name' => 'Edit Roles', 'group' => 'roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'group' => 'roles'],

            // Permission Management
            ['name' => 'permissions.view', 'display_name' => 'View Permissions', 'group' => 'permissions'],
            ['name' => 'permissions.create', 'display_name' => 'Create Permissions', 'group' => 'permissions'],
            ['name' => 'permissions.edit', 'display_name' => 'Edit Permissions', 'group' => 'permissions'],
            ['name' => 'permissions.delete', 'display_name' => 'Delete Permissions', 'group' => 'permissions'],

            // Menu Management
            ['name' => 'menus.view', 'display_name' => 'View Menus', 'group' => 'menus'],
            ['name' => 'menus.create', 'display_name' => 'Create Menus', 'group' => 'menus'],
            ['name' => 'menus.edit', 'display_name' => 'Edit Menus', 'group' => 'menus'],
            ['name' => 'menus.delete', 'display_name' => 'Delete Menus', 'group' => 'menus'],

            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'group' => 'dashboard'],
            ['name' => 'dashboard.analytics', 'display_name' => 'View Analytics', 'group' => 'dashboard'],

            // Reports
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'group' => 'reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'group' => 'reports'],

            // Settings
            ['name' => 'settings.view', 'display_name' => 'View Settings', 'group' => 'settings'],
            ['name' => 'settings.edit', 'display_name' => 'Edit Settings', 'group' => 'settings'],
            ['name' => 'settings.manage', 'display_name' => 'Manage Site Settings', 'group' => 'settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        $this->command->info('✅ Permissions created: ' . count($permissions));
    }

    private function createRoles()
    {
        $this->command->info('👥 Creating roles...');

        // Superadmin Role - has ALL permissions
        $superadmin = Role::firstOrCreate(
            ['name' => 'superadmin'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Has full access to everything',
                'is_active' => true,
            ]
        );
        // Superadmin gets all permissions
        $superadmin->permissions()->sync(Permission::all()->pluck('id'));

        // Admin Role
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description' => 'Has access to most features',
                'is_active' => true,
            ]
        );
        $adminPermissions = Permission::whereIn('group', ['users', 'dashboard', 'reports', 'settings'])->pluck('id');
        $admin->permissions()->sync($adminPermissions);

        // Manager Role
        $manager = Role::firstOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Manager',
                'description' => 'Can manage users and view reports',
                'is_active' => true,
            ]
        );
        $managerPermissions = Permission::whereIn('name', [
            'users.view', 'users.edit',
            'dashboard.view', 'dashboard.analytics',
            'reports.view', 'reports.export'
        ])->pluck('id');
        $manager->permissions()->sync($managerPermissions);

        // User Role
        $user = Role::firstOrCreate(
            ['name' => 'user'],
            [
                'display_name' => 'User',
                'description' => 'Basic user access',
                'is_active' => true,
            ]
        );
        $userPermissions = Permission::whereIn('name', ['dashboard.view'])->pluck('id');
        $user->permissions()->sync($userPermissions);

        $this->command->info('✅ Roles created: 4');

        return compact('superadmin', 'admin', 'manager', 'user');
    }

    private function createMenus()
    {
        $this->command->info('📋 Creating menus...');

        // Dashboard
        $dashboard = Menu::firstOrCreate(
            ['name' => 'dashboard'],
            [
                'title' => 'Dashboard',
                'icon' => 'pi pi-home',
                'route' => '/admin/dashboard',
                'url' => '/admin/dashboard',
                'order' => 1,
                'is_active' => true,
            ]
        );
        $dashboard->permissions()->sync(Permission::where('name', 'dashboard.view')->pluck('id'));

        // User Management
        $userManagement = Menu::firstOrCreate(
            ['name' => 'user-management'],
            [
                'title' => 'User Management',
                'icon' => 'pi pi-users',
                'route' => '/admin/users',
                'url' => '/admin/users',
                'order' => 2,
                'is_active' => true,
            ]
        );
        $userManagement->permissions()->sync(Permission::where('name', 'users.view')->pluck('id'));

        // Role Management
        $roleManagement = Menu::firstOrCreate(
            ['name' => 'role-management'],
            [
                'title' => 'Role Management',
                'icon' => 'pi pi-shield',
                'route' => '/admin/roles',
                'url' => '/admin/roles',
                'order' => 3,
                'is_active' => true,
            ]
        );
        $roleManagement->permissions()->sync(Permission::where('name', 'roles.view')->pluck('id'));

        // Permission Management
        $permissionManagement = Menu::firstOrCreate(
            ['name' => 'permission-management'],
            [
                'title' => 'Permission Management',
                'icon' => 'pi pi-lock',
                'route' => '/admin/permissions',
                'url' => '/admin/permissions',
                'order' => 4,
                'is_active' => true,
            ]
        );
        $permissionManagement->permissions()->sync(Permission::where('name', 'permissions.view')->pluck('id'));

        // Menu Management
        $menuManagement = Menu::firstOrCreate(
            ['name' => 'menu-management'],
            [
                'title' => 'Menu Management',
                'icon' => 'pi pi-bars',
                'route' => '/admin/menus',
                'url' => '/admin/menus',
                'order' => 5,
                'is_active' => true,
            ]
        );
        $menuManagement->permissions()->sync(Permission::where('name', 'menus.view')->pluck('id'));

        // Reports
        $reports = Menu::firstOrCreate(
            ['name' => 'reports'],
            [
                'title' => 'Reports',
                'icon' => 'pi pi-chart-bar',
                'route' => '/admin/reports',
                'url' => '/admin/reports',
                'order' => 6,
                'is_active' => true,
            ]
        );
        $reports->permissions()->sync(Permission::where('name', 'reports.view')->pluck('id'));

        // Settings menu is outlet-scoped only; seeded via OutletDashboardMenuSeeder.
        // Drop the legacy site-level entry if it exists.
        Menu::where('name', 'settings')->delete();

        // Site Settings (superadmin — Pengaturan Situs)
        $siteSettings = Menu::firstOrCreate(
            ['name' => 'site-settings'],
            [
                'title' => 'Pengaturan Situs',
                'icon' => 'pi pi-globe',
                'route' => '/admin/site-settings',
                'url' => '/admin/site-settings',
                'order' => 10,
                'is_active' => true,
            ]
        );
        $siteSettings->permissions()->sync(
            Permission::whereIn('name', ['settings.manage', 'settings.edit'])->pluck('id')
        );

        $this->command->info('✅ Menus created: 8');
    }

    private function createUsers($roles)
    {
        $this->command->info('👤 Creating users...');

        // Superadmin
        $superadmin = User::firstOrCreate(
            ['email' => 'admin@saasapp.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $superadmin->roles()->sync([$roles['superadmin']->id]);

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin2@saasapp.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->roles()->sync([$roles['admin']->id]);

        // Manager
        $manager = User::firstOrCreate(
            ['email' => 'manager@saasapp.com'],
            [
                'name' => 'Manager User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $manager->roles()->sync([$roles['manager']->id]);

        // Regular User
        $user = User::firstOrCreate(
            ['email' => 'user@saasapp.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $user->roles()->sync([$roles['user']->id]);

        $this->command->info('✅ Users created: 4');
        $this->command->newLine();
        $this->command->info('📧 Login Credentials:');
        $this->command->table(
            ['Email', 'Password', 'Role'],
            [
                ['admin@saasapp.com', 'password', 'Superadmin'],
                ['admin2@saasapp.com', 'password', 'Admin'],
                ['manager@saasapp.com', 'password', 'Manager'],
                ['user@saasapp.com', 'password', 'User'],
            ]
        );
        $this->command->warn('⚠️  Please change passwords after first login!');
    }
}
