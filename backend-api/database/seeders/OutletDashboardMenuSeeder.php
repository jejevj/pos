<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;

class OutletDashboardMenuSeeder extends Seeder
{
    public function run(): void
    {
        $outletId = 1; // default outlet

        $menus = [
            [
                'name'      => 'outlet-dashboard',
                'title'     => 'Dashboard',
                'icon'      => 'pi pi-home',
                'url'       => "/outlets/{$outletId}/dashboard",
                'order'     => 1,
                'parent_id' => null,
            ],
            [
                'name'      => 'outlet-pos',
                'title'     => 'POS / Kasir',
                'icon'      => 'pi pi-shopping-cart',
                'url'       => "/outlets/{$outletId}/pos",
                'order'     => 2,
                'parent_id' => null,
            ],
            [
                'name'      => 'outlet-transactions',
                'title'     => 'Transaksi',
                'icon'      => 'pi pi-receipt',
                'url'       => "/outlets/{$outletId}/transactions",
                'order'     => 3,
                'parent_id' => null,
            ],
            // Inventory group
            [
                'name'      => 'outlet-inventory',
                'title'     => 'Inventori',
                'icon'      => 'pi pi-box',
                'url'       => null,
                'order'     => 10,
                'parent_id' => null,
                'children'  => [
                    ['name' => 'outlet-bahan-baku',        'title' => 'Bahan Baku',       'icon' => 'pi pi-list',        'url' => "/outlets/{$outletId}/bahan-baku",        'order' => 1],
                    ['name' => 'outlet-stock-locations',   'title' => 'Lokasi Stok',      'icon' => 'pi pi-building',    'url' => "/outlets/{$outletId}/stock-locations",   'order' => 2],
                    ['name' => 'outlet-stock-opname',      'title' => 'Stock Opname',     'icon' => 'pi pi-clipboard',   'url' => "/outlets/{$outletId}/stock-opname",      'order' => 3],
                    ['name' => 'outlet-purchases',         'title' => 'Barang Masuk',     'icon' => 'pi pi-arrow-down',  'url' => "/outlets/{$outletId}/purchases",         'order' => 4],
                ],
            ],
            // Menu & Promo group
            [
                'name'      => 'outlet-menu-group',
                'title'     => 'Menu & Promo',
                'icon'      => 'pi pi-book',
                'url'       => null,
                'order'     => 20,
                'parent_id' => null,
                'children'  => [
                    ['name' => 'outlet-menu',          'title' => 'Menu',         'icon' => 'pi pi-book',       'url' => "/outlets/{$outletId}/menu",          'order' => 1],
                    ['name' => 'outlet-promos',        'title' => 'Promo',        'icon' => 'pi pi-tag',        'url' => "/outlets/{$outletId}/promos",        'order' => 2],
                    ['name' => 'outlet-members',       'title' => 'Member',       'icon' => 'pi pi-users',      'url' => "/outlets/{$outletId}/members",       'order' => 3],
                ],
            ],
            // HR group
            [
                'name'      => 'outlet-hr-group',
                'title'     => 'SDM',
                'icon'      => 'pi pi-users',
                'url'       => null,
                'order'     => 30,
                'parent_id' => null,
                'children'  => [
                    ['name' => 'outlet-hr',         'title' => 'Karyawan',      'icon' => 'pi pi-user',        'url' => "/outlets/{$outletId}/hr",         'order' => 1],
                    ['name' => 'outlet-shifts',     'title' => 'Shift',         'icon' => 'pi pi-calendar',    'url' => "/outlets/{$outletId}/shifts",     'order' => 2],
                    ['name' => 'outlet-attendance', 'title' => 'Absensi',       'icon' => 'pi pi-clock',       'url' => "/outlets/{$outletId}/attendance", 'order' => 3],
                ],
            ],
            // Finance group
            [
                'name'      => 'outlet-finance-group',
                'title'     => 'Keuangan',
                'icon'      => 'pi pi-wallet',
                'url'       => null,
                'order'     => 40,
                'parent_id' => null,
                'children'  => [
                    ['name' => 'outlet-expenses',        'title' => 'Pengeluaran',       'icon' => 'pi pi-minus-circle', 'url' => "/outlets/{$outletId}/expenses",        'order' => 1],
                    ['name' => 'outlet-payment-methods', 'title' => 'Metode Pembayaran', 'icon' => 'pi pi-credit-card',  'url' => "/outlets/{$outletId}/payment-methods", 'order' => 2],
                    ['name' => 'outlet-reports',         'title' => 'Laporan',           'icon' => 'pi pi-chart-bar',    'url' => "/outlets/{$outletId}/reports",         'order' => 3],
                ],
            ],
            // Settings group
            [
                'name'      => 'outlet-settings-group',
                'title'     => 'Pengaturan',
                'icon'      => 'pi pi-cog',
                'url'       => null,
                'order'     => 50,
                'parent_id' => null,
                'children'  => [
                    ['name' => 'outlet-tables',    'title' => 'Meja',      'icon' => 'pi pi-table',    'url' => "/outlets/{$outletId}/tables",    'order' => 1],
                    ['name' => 'outlet-stations',  'title' => 'Stasiun',   'icon' => 'pi pi-server',   'url' => "/outlets/{$outletId}/stations",  'order' => 2],
                    ['name' => 'outlet-users',     'title' => 'Pengguna',  'icon' => 'pi pi-user-plus','url' => "/outlets/{$outletId}/users",     'order' => 3],
                    ['name' => 'outlet-utilities', 'title' => 'Utilitas',  'icon' => 'pi pi-wrench',   'url' => "/outlets/{$outletId}/utilities", 'order' => 4],
                    ['name' => 'outlet-whatsapp',  'title' => 'WhatsApp',  'icon' => 'pi pi-whatsapp', 'url' => "/outlets/{$outletId}/whatsapp",  'order' => 5],
                ],
            ],
        ];

        foreach ($menus as $menuData) {
            $children = $menuData['children'] ?? [];
            unset($menuData['children']);

            $parent = Menu::updateOrCreate(
                ['name' => $menuData['name']],
                array_merge($menuData, ['is_active' => true, 'route' => $menuData['url'] ?? ''])
            );

            foreach ($children as $child) {
                Menu::updateOrCreate(
                    ['name' => $child['name']],
                    array_merge($child, [
                        'is_active' => true,
                        'route'     => $child['url'],
                        'parent_id' => $parent->id,
                    ])
                );
            }
        }

        $this->command->info('Outlet dashboard menus seeded!');
    }
}
