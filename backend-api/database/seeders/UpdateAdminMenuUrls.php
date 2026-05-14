<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateAdminMenuUrls extends Seeder
{
    public function run(): void
    {
        $updates = [
            'dashboard'             => '/admin/dashboard',
            'user-management'       => '/admin/users',
            'role-management'       => '/admin/roles',
            'permission-management' => '/admin/permissions',
            'menu-management'       => '/admin/menus',
            'site-settings'         => '/admin/site-settings',
            'reports'               => '/admin/reports',
            'settings'              => '/admin/settings',
        ];

        foreach ($updates as $name => $url) {
            DB::table('menus')
                ->where('name', $name)
                ->update(['url' => $url, 'route' => $url]);
        }
    }
}
