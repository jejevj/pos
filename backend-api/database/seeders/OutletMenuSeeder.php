<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;

class OutletMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Outlet Permissions
        $permissions = [
            // Outlet Management
            ['name' => 'outlets.view', 'display_name' => 'View Outlets', 'group' => 'outlets', 'description' => 'Can view outlets list'],
            ['name' => 'outlets.create', 'display_name' => 'Create Outlet', 'group' => 'outlets', 'description' => 'Can create new outlets'],
            ['name' => 'outlets.edit', 'display_name' => 'Edit Outlet', 'group' => 'outlets', 'description' => 'Can edit outlet details'],
            ['name' => 'outlets.delete', 'display_name' => 'Delete Outlet', 'group' => 'outlets', 'description' => 'Can delete outlets'],
            
            // Outlet Users Management
            ['name' => 'outlet_users.view', 'display_name' => 'View Outlet Users', 'group' => 'outlet_users', 'description' => 'Can view outlet users list'],
            ['name' => 'outlet_users.create', 'display_name' => 'Create Outlet User', 'group' => 'outlet_users', 'description' => 'Can create new outlet users'],
            ['name' => 'outlet_users.edit', 'display_name' => 'Edit Outlet User', 'group' => 'outlet_users', 'description' => 'Can edit outlet user details'],
            ['name' => 'outlet_users.delete', 'display_name' => 'Delete Outlet User', 'group' => 'outlet_users', 'description' => 'Can delete outlet users'],
        ];

        $createdPermissions = [];
        foreach ($permissions as $permData) {
            $permission = Permission::firstOrCreate(
                ['name' => $permData['name']],
                $permData
            );
            $createdPermissions[$permData['name']] = $permission;
        }

        // Create Outlet Management Menu
        $outletMenu = Menu::firstOrCreate(
            ['name' => 'outlets'],
            [
                'title' => 'Outlet Management',
                'icon' => 'pi pi-building',
                'route' => '/outlets',
                'url' => '/outlets',
                'parent_id' => null,
                'order' => 50,
                'is_active' => true,
            ]
        );

        // Attach permissions to Outlet Management menu
        $outletMenu->permissions()->sync([
            $createdPermissions['outlets.view']->id,
            $createdPermissions['outlets.create']->id,
            $createdPermissions['outlets.edit']->id,
            $createdPermissions['outlets.delete']->id,
        ]);

        // Assign all outlet permissions to superadmin role
        $superadminRole = Role::where('name', 'superadmin')->first();
        if ($superadminRole) {
            $permissionIds = array_map(fn($p) => $p->id, $createdPermissions);
            $existingPermissions = $superadminRole->permissions()->pluck('permissions.id')->toArray();
            $allPermissions = array_unique(array_merge($existingPermissions, $permissionIds));
            $superadminRole->permissions()->sync($allPermissions);
        }

        // Assign outlet permissions to admin role (except delete)
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminPermissions = [
                $createdPermissions['outlets.view']->id,
                $createdPermissions['outlets.create']->id,
                $createdPermissions['outlets.edit']->id,
                $createdPermissions['outlet_users.view']->id,
                $createdPermissions['outlet_users.create']->id,
                $createdPermissions['outlet_users.edit']->id,
            ];
            $existingPermissions = $adminRole->permissions()->pluck('permissions.id')->toArray();
            $allPermissions = array_unique(array_merge($existingPermissions, $adminPermissions));
            $adminRole->permissions()->sync($allPermissions);
        }

        // Assign basic outlet view permission to user role
        $userRole = Role::where('name', 'user')->first();
        if ($userRole) {
            $userPermissions = [
                $createdPermissions['outlets.view']->id,
                $createdPermissions['outlets.create']->id,
                $createdPermissions['outlet_users.view']->id,
            ];
            $existingPermissions = $userRole->permissions()->pluck('permissions.id')->toArray();
            $allPermissions = array_unique(array_merge($existingPermissions, $userPermissions));
            $userRole->permissions()->sync($allPermissions);
        }

        $this->command->info('Outlet menus and permissions seeded successfully!');
    }
}
