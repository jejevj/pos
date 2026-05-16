<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Services\OutletDefaultDataSeeder;

/**
 * Idempotent provisioner for everything that lives inside a per-outlet
 * PostgreSQL schema.
 *
 * Centralises what previously required running a chain of artisan
 * commands by hand (`outlets:create-schemas`, `create-rbac-tables`,
 * `seed-rbac`, `create-transaction-tables`, `create-menu-tables`,
 * `create-station-tables`, `create-bahan-baku-tables`,
 * `create-stock-opname-tables`, `create-promo-tables`,
 * `create-membership-tables`, `outlet:create-hr-tables`,
 * `outlet:create-shift-tables`, `create:kasbon-tables`,
 * `create:purchase-expense-tables`,
 * `outlets:create-employee-beverage-tables`, `outlets:add-identity-columns`,
 * `outlets:add-location`, `promos:add-stackable-column`,
 * `orders:add-applied-promos-column`, `outlet:add-day-off-column`,
 * `outlets:update-bahan-baku-columns`).
 *
 * Every statement uses CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT
 * EXISTS, so this is safe to call on every outlet on every boot, on
 * outlet creation, and on demand via `outlets:provision`.
 *
 * NOTE: This service never invokes `Artisan::call(...)` — calling
 * commands from model events is brittle (recursion, missing input,
 * different lifecycle). Commands now delegate here instead.
 */
class OutletProvisioner
{
    /**
     * Provision the full per-outlet schema. Safe to call repeatedly.
     *
     * Steps run in dependency order so re-runs heal partially-provisioned
     * outlets (e.g. one that only got `outlet_users`).
     */
    public function provision(Outlet $outlet): bool
    {
        try {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$outlet->schema_name}");

            $this->ensureOutletUsersTable($outlet);
            $this->ensureRbacTables($outlet);
            $this->seedRbac($outlet);
            $this->ensureTransactionTables($outlet);
            $this->ensureTransactionSettingsTable($outlet);
            $this->ensureMenuTables($outlet);
            $this->ensureBahanBakuTables($outlet);
            $this->ensureStationTables($outlet);
            $this->ensureStockOpnameTables($outlet);
            $this->ensurePromoTables($outlet);
            $this->ensureMembershipTables($outlet);
            $this->ensureHrTables($outlet);
            $this->ensureShiftTables($outlet);
            $this->ensureKasbonTables($outlet);
            $this->ensurePurchaseExpenseTables($outlet);
            $this->ensureEmployeeBeverageTables($outlet);
            $this->ensureProductionTables($outlet);
            $this->ensureCrossTableFks($outlet);

            // Seed default master data (satuan, kategori, bahan baku, menu)
            // Idempotent — aman dipanggil ulang, skip jika data sudah ada
            (new OutletDefaultDataSeeder())->seed($outlet->schema_name);

            return true;
        } catch (\Throwable $e) {
            Log::error("OutletProvisioner failed for outlet {$outlet->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        } finally {
            DB::statement('SET search_path TO public');
        }
    }

    /**
     * Ensure the creating global user has a matching `outlet_users` row in
     * this outlet's schema, with the `owner` role attached.
     *
     * Matched by email (case-insensitive). If no match exists we insert a
     * new row using the user's name/email. The user's `users.password` is
     * already a bcrypt hash, so we reuse it verbatim — the user can log in
     * with the same credentials they already use.
     *
     * Superadmins are NOT auto-mapped here. Superadmin gets a row only for
     * outlets they personally create (the call site is responsible for
     * passing the creator). This prevents the superadmin account from
     * silently becoming an employee of every outlet in the system.
     */
    public function mapOwner(Outlet $outlet, User $creator): void
    {
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $ownerRoleId = DB::table('roles')->where('name', 'owner')->value('id');

            $existing = DB::table('outlet_users')
                ->whereRaw('LOWER(email) = ?', [strtolower($creator->email)])
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                $update = ['updated_at' => now()];
                if ($ownerRoleId && empty($existing->role_id)) {
                    $update['role_id'] = $ownerRoleId;
                }
                DB::table('outlet_users')->where('id', $existing->id)->update($update);
                $outletUserId = $existing->id;
            } else {
                $outletUserId = DB::table('outlet_users')->insertGetId([
                    'outlet_id' => $outlet->id,
                    'name' => $creator->name ?: $creator->email,
                    'email' => $creator->email,
                    'password' => $creator->password ?: Hash::make(bin2hex(random_bytes(16))),
                    'role_id' => $ownerRoleId,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($ownerRoleId) {
                $hasUserRole = DB::table('user_roles')
                    ->where('user_id', $outletUserId)
                    ->where('role_id', $ownerRoleId)
                    ->exists();
                if (!$hasUserRole) {
                    DB::table('user_roles')->insert([
                        'user_id' => $outletUserId,
                        'role_id' => $ownerRoleId,
                        'created_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error("OutletProvisioner::mapOwner failed for outlet {$outlet->id}, user {$creator->id}: " . $e->getMessage());
        } finally {
            DB::statement('SET search_path TO public');
        }
    }

    private function ensureOutletUsersTable(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.outlet_users (
                id SERIAL PRIMARY KEY,
                outlet_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                phone VARCHAR(255),
                role VARCHAR(50) DEFAULT 'staff',
                is_active BOOLEAN DEFAULT true,
                settings JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_outlet_users_email ON {$schema}.outlet_users(email)");
    }

    private function ensureRbacTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.roles (
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
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.permissions (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) UNIQUE NOT NULL,
                display_name VARCHAR(150) NOT NULL,
                group_name VARCHAR(50) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.role_permissions (
                id SERIAL PRIMARY KEY,
                role_id INTEGER NOT NULL REFERENCES {$schema}.roles(id) ON DELETE CASCADE,
                permission_id INTEGER NOT NULL REFERENCES {$schema}.permissions(id) ON DELETE CASCADE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(role_id, permission_id)
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.user_roles (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                role_id INTEGER NOT NULL REFERENCES {$schema}.roles(id) ON DELETE CASCADE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, role_id)
            )
        ");

        if (!$this->columnExists($schema, 'outlet_users', 'role_id')) {
            DB::statement("ALTER TABLE {$schema}.outlet_users ADD COLUMN role_id INTEGER");
            DB::statement("ALTER TABLE {$schema}.outlet_users ADD CONSTRAINT fk_outlet_users_role FOREIGN KEY (role_id) REFERENCES {$schema}.roles(id) ON DELETE SET NULL");
        }
    }

    private function seedRbac(Outlet $outlet): void
    {
        DB::statement("SET search_path TO {$outlet->schema_name}, public");

        $permissions = [
            ['view_dashboard', 'View Dashboard', 'Dashboard'],
            ['view_inventory', 'View Inventory', 'Inventory'],
            ['create_inventory', 'Create Inventory', 'Inventory'],
            ['edit_inventory', 'Edit Inventory', 'Inventory'],
            ['delete_inventory', 'Delete Inventory', 'Inventory'],
            ['view_menu', 'View Menu', 'Menu'],
            ['create_menu', 'Create Menu', 'Menu'],
            ['edit_menu', 'Edit Menu', 'Menu'],
            ['delete_menu', 'Delete Menu', 'Menu'],
            ['access_pos', 'Access POS', 'POS'],
            ['create_order', 'Create Order', 'POS'],
            ['cancel_order', 'Cancel Order', 'POS'],
            ['apply_discount', 'Apply Discount', 'POS'],
            ['view_transactions', 'View Transactions', 'Transactions'],
            ['refund_transaction', 'Refund Transaction', 'Transactions'],
            ['view_reports', 'View Reports', 'Reports'],
            ['export_reports', 'Export Reports', 'Reports'],
            ['view_employees', 'View Employees', 'HR'],
            ['create_employee', 'Create Employee', 'HR'],
            ['edit_employee', 'Edit Employee', 'HR'],
            ['delete_employee', 'Delete Employee', 'HR'],
            ['view_attendance', 'View Attendance', 'HR'],
            ['manage_payroll', 'Manage Payroll', 'HR'],
            ['manage_kasbon', 'Manage Kasbon', 'HR'],
            ['approve_leave', 'Approve Leave', 'HR'],
            ['view_purchases', 'View Purchases', 'Finance'],
            ['create_purchase', 'Create Purchase', 'Finance'],
            ['view_expenses', 'View Expenses', 'Finance'],
            ['create_expense', 'Create Expense', 'Finance'],
            ['view_members', 'View Members', 'Members'],
            ['create_member', 'Create Member', 'Members'],
            ['edit_member', 'Edit Member', 'Members'],
            ['view_promos', 'View Promos', 'Marketing'],
            ['create_promo', 'Create Promo', 'Marketing'],
            ['edit_promo', 'Edit Promo', 'Marketing'],
            ['delete_promo', 'Delete Promo', 'Marketing'],
            ['view_settings', 'View Settings', 'Settings'],
            ['edit_settings', 'Edit Settings', 'Settings'],
            ['manage_roles', 'Manage Roles & Permissions', 'Settings'],
            ['manage_users', 'Manage Users', 'Settings'],
            ['view_stock_opname', 'View Stock Opname', 'Inventory'],
            ['create_stock_opname', 'Create Stock Opname', 'Inventory'],
            ['manage_tables', 'Manage Tables', 'Settings'],
            ['access_kitchen_display', 'Access Kitchen Display', 'Kitchen'],
            ['view_production', 'Lihat Unit Produksi', 'production'],
            ['manage_production', 'Kelola Unit Produksi', 'production'],
        ];
        foreach ($permissions as [$name, $display, $group]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'display_name' => $display,
                    'group_name' => $group,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $allPerms = DB::table('permissions')->pluck('id')->all();
        $byName = fn(array $names) => DB::table('permissions')->whereIn('name', $names)->pluck('id')->all();

        $roles = [
            ['owner', 'Owner', 'Full access to all features', 100, $allPerms],
            ['admin', 'Administrator', 'Administrative access with most permissions', 90, $byName([
                'view_dashboard', 'view_inventory', 'create_inventory', 'edit_inventory',
                'view_menu', 'create_menu', 'edit_menu', 'access_pos', 'create_order',
                'cancel_order', 'apply_discount', 'view_transactions', 'view_reports',
                'export_reports', 'view_employees', 'create_employee', 'edit_employee',
                'view_attendance', 'manage_payroll', 'manage_kasbon', 'approve_leave',
                'view_purchases', 'create_purchase', 'view_expenses', 'create_expense',
                'view_members', 'create_member', 'edit_member', 'view_promos',
                'create_promo', 'edit_promo', 'view_settings', 'edit_settings',
                'manage_users', 'view_stock_opname', 'create_stock_opname', 'manage_tables',
            ])],
            ['manager', 'Manager', 'Manage daily operations', 70, $byName([
                'view_dashboard', 'view_inventory', 'create_inventory', 'edit_inventory',
                'view_menu', 'access_pos', 'create_order', 'cancel_order',
                'apply_discount', 'view_transactions', 'view_reports',
                'view_employees', 'view_attendance', 'approve_leave',
                'view_purchases', 'create_purchase', 'view_expenses', 'create_expense',
                'view_members', 'create_member', 'view_promos', 'view_stock_opname',
                'create_stock_opname',
                'view_production', 'manage_production',
            ])],
            ['production', 'Unit Produksi', 'Operator unit produksi', 45, $byName([
                'view_production', 'manage_production', 'view_inventory', 'view_dashboard',
            ])],
            ['cashier', 'Cashier', 'Handle POS and transactions', 50, $byName([
                'view_dashboard', 'access_pos', 'create_order', 'view_transactions',
                'view_members', 'create_member', 'view_menu',
            ])],
            ['kitchen_staff', 'Kitchen Staff', 'Access kitchen display', 40, $byName([
                'access_kitchen_display', 'view_menu',
            ])],
            ['staff', 'Staff', 'Basic staff access', 30, $byName([
                'view_dashboard', 'view_menu',
            ])],
        ];

        foreach ($roles as [$name, $display, $desc, $level, $perms]) {
            $existing = DB::table('roles')->where('name', $name)->first();
            if ($existing) {
                $roleId = $existing->id;
            } else {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => $name,
                    'display_name' => $display,
                    'description' => $desc,
                    'level' => $level,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($perms as $pid) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $pid],
                    ['created_at' => now()]
                );
            }
        }

        DB::statement('SET search_path TO public');
    }

    private function ensureTransactionTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.tables (
                id BIGSERIAL PRIMARY KEY,
                table_number VARCHAR(50) NOT NULL,
                capacity INTEGER DEFAULT 4,
                area VARCHAR(50) DEFAULT 'indoor',
                status VARCHAR(20) DEFAULT 'available',
                is_active BOOLEAN DEFAULT true,
                qr_token VARCHAR(64) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL
            )
        ");
        // Heal tables.qr_token + unique index for older schemas
        DB::statement("ALTER TABLE {$schema}.tables ADD COLUMN IF NOT EXISTS qr_token VARCHAR(64) NULL");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS idx_{$schema}_tables_qr_token ON {$schema}.tables(qr_token) WHERE qr_token IS NOT NULL");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.payment_methods (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(50) NOT NULL,
                icon VARCHAR(255) NULL,
                is_active BOOLEAN DEFAULT true,
                display_order INTEGER DEFAULT 0,
                is_online_orderable BOOLEAN DEFAULT FALSE,
                qr_image_path VARCHAR(500) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL
            )
        ");
        // Heal existing schemas
        DB::statement("ALTER TABLE {$schema}.payment_methods ADD COLUMN IF NOT EXISTS is_online_orderable BOOLEAN DEFAULT FALSE");
        DB::statement("ALTER TABLE {$schema}.payment_methods ADD COLUMN IF NOT EXISTS qr_image_path VARCHAR(500) NULL");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.orders (
                id BIGSERIAL PRIMARY KEY,
                kode VARCHAR(50) UNIQUE NOT NULL,
                order_type VARCHAR(20) NOT NULL,
                table_id BIGINT NULL REFERENCES {$schema}.tables(id) ON DELETE SET NULL,
                table_number VARCHAR(50) NULL,
                customer_name VARCHAR(255) NULL,
                customer_phone VARCHAR(50) NULL,
                status VARCHAR(20) DEFAULT 'draft',
                subtotal DECIMAL(15,2) DEFAULT 0,
                promo_id BIGINT NULL,
                promo_code VARCHAR(50) NULL,
                discount_type VARCHAR(20) NULL,
                discount_value DECIMAL(15,2) DEFAULT 0,
                discount_amount DECIMAL(15,2) DEFAULT 0,
                tax_percentage DECIMAL(5,2) DEFAULT 11,
                tax_amount DECIMAL(15,2) DEFAULT 0,
                service_charge_percentage DECIMAL(5,2) NULL,
                service_charge_amount DECIMAL(15,2) DEFAULT 0,
                total_amount DECIMAL(15,2) DEFAULT 0,
                payment_method_id BIGINT NULL REFERENCES {$schema}.payment_methods(id) ON DELETE SET NULL,
                paid_amount DECIMAL(15,2) DEFAULT 0,
                change_amount DECIMAL(15,2) DEFAULT 0,
                notes TEXT NULL,
                cashier_id BIGINT NOT NULL,
                paid_at TIMESTAMP NULL,
                cancelled_at TIMESTAMP NULL,
                cancelled_by BIGINT NULL,
                cancellation_reason TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.order_items (
                id BIGSERIAL PRIMARY KEY,
                order_id BIGINT NOT NULL REFERENCES {$schema}.orders(id) ON DELETE CASCADE,
                menu_id BIGINT NOT NULL,
                menu_name VARCHAR(255) NOT NULL,
                menu_price DECIMAL(15,2) NOT NULL,
                quantity INTEGER NOT NULL,
                subtotal DECIMAL(15,2) NOT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // applied_promos JSONB on orders
        if (!$this->columnExists($schema, 'orders', 'applied_promos')) {
            DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN applied_promos JSONB DEFAULT '[]'::jsonb");
        }

        // Public table-order columns (source, approval flow, contact email)
        DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT 'pos'");
        DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS approval_status VARCHAR(20) NULL");
        DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS approved_by BIGINT NULL");
        DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL");
        DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS rejected_by BIGINT NULL");
        DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP NULL");
        DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL");
        DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS customer_email VARCHAR(255) NULL");
        DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS payment_proof_path VARCHAR(500) NULL");
        DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN IF NOT EXISTS payment_proof_uploaded_at TIMESTAMP NULL");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_{$schema}_orders_source_approval ON {$schema}.orders(source, approval_status)");
        // Allow cashier_id NULL for public-source orders
        DB::statement("ALTER TABLE {$schema}.orders ALTER COLUMN cashier_id DROP NOT NULL");

        // Seed defaults
        DB::statement("SET search_path TO {$schema}, public");
        if (DB::table('payment_methods')->count() == 0) {
            DB::table('payment_methods')->insert([
                ['name' => 'Cash', 'code' => 'cash', 'display_order' => 1, 'is_active' => true, 'is_online_orderable' => false],
                ['name' => 'Debit Card', 'code' => 'debit_card', 'display_order' => 2, 'is_active' => true, 'is_online_orderable' => false],
                ['name' => 'Credit Card', 'code' => 'credit_card', 'display_order' => 3, 'is_active' => true, 'is_online_orderable' => false],
                ['name' => 'E-Wallet', 'code' => 'e_wallet', 'display_order' => 4, 'is_active' => true, 'is_online_orderable' => false],
                ['name' => 'Transfer Bank', 'code' => 'bank_transfer', 'display_order' => 5, 'is_active' => true, 'is_online_orderable' => false],
                ['name' => 'QRIS', 'code' => 'qris', 'display_order' => 6, 'is_active' => true, 'is_online_orderable' => true],
            ]);
        }
        if (DB::table('tables')->count() == 0) {
            $rows = [];
            for ($i = 1; $i <= 10; $i++) {
                $rows[] = [
                    'table_number' => (string) $i,
                    'capacity' => $i <= 5 ? 4 : 6,
                    'area' => $i <= 7 ? 'indoor' : 'outdoor',
                    'status' => 'available',
                    'is_active' => true,
                ];
            }
            DB::table('tables')->insert($rows);
        }
        DB::statement('SET search_path TO public');
    }

    /**
     * Seed the per-outlet `transaction_settings` table with a default row.
     * Mirrors the table shape in TransactionSettingController::ensureTable
     * so a brand-new outlet already has settings before the API is ever hit.
     */
    private function ensureTransactionSettingsTable(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        $existed = $this->tableExists($schema, 'transaction_settings');

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.transaction_settings (
                id                          SERIAL PRIMARY KEY,
                tax_enabled                 BOOLEAN DEFAULT true,
                tax_percentage              DECIMAL(5,2) DEFAULT 11,
                tax_label                   VARCHAR(50) DEFAULT 'PPN',
                tax_inclusive               BOOLEAN DEFAULT false,
                service_charge_enabled      BOOLEAN DEFAULT false,
                service_charge_percentage   DECIMAL(5,2) DEFAULT 0,
                service_charge_label        VARCHAR(50) DEFAULT 'Service Charge',
                min_order_amount            DECIMAL(15,2) DEFAULT 0,
                receipt_logo_enabled        BOOLEAN DEFAULT true,
                receipt_header              TEXT DEFAULT '',
                receipt_footer              TEXT DEFAULT '',
                receipt_show_qr             BOOLEAN DEFAULT true,
                receipt_wifi_enabled        BOOLEAN DEFAULT false,
                receipt_wifi_ssid           VARCHAR(100) DEFAULT '',
                receipt_wifi_password       VARCHAR(100) DEFAULT '',
                receipt_show_cashier        BOOLEAN DEFAULT true,
                receipt_show_table          BOOLEAN DEFAULT true,
                receipt_show_member         BOOLEAN DEFAULT true,
                receipt_custom_logo_url     TEXT DEFAULT '',
                created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Heal pre-existing tables that may be missing newer columns.
        $columns = [
            'receipt_logo_enabled'    => 'BOOLEAN DEFAULT true',
            'receipt_header'          => "TEXT DEFAULT ''",
            'receipt_footer'          => "TEXT DEFAULT ''",
            'receipt_show_qr'         => 'BOOLEAN DEFAULT true',
            'receipt_wifi_enabled'    => 'BOOLEAN DEFAULT false',
            'receipt_wifi_ssid'       => "VARCHAR(100) DEFAULT ''",
            'receipt_wifi_password'   => "VARCHAR(100) DEFAULT ''",
            'receipt_show_cashier'    => 'BOOLEAN DEFAULT true',
            'receipt_show_table'      => 'BOOLEAN DEFAULT true',
            'receipt_show_member'     => 'BOOLEAN DEFAULT true',
            'receipt_custom_logo_url' => "TEXT DEFAULT ''",
            'min_order_amount'        => 'DECIMAL(15,2) DEFAULT 0',
        ];
        foreach ($columns as $col => $def) {
            DB::statement("ALTER TABLE {$schema}.transaction_settings ADD COLUMN IF NOT EXISTS {$col} {$def}");
        }

        // Insert the default row only on first creation. If the table already
        // existed but is empty (older outlet provisioned before this seed was
        // added), backfill a default row as well.
        $hasRow = DB::table("{$schema}.transaction_settings")->exists();
        if (!$existed || !$hasRow) {
            DB::statement("
                INSERT INTO {$schema}.transaction_settings
                    (tax_enabled, tax_percentage, tax_label, tax_inclusive,
                     service_charge_enabled, service_charge_percentage, service_charge_label,
                     min_order_amount,
                     receipt_logo_enabled, receipt_header, receipt_footer,
                     receipt_show_qr, receipt_wifi_enabled, receipt_wifi_ssid, receipt_wifi_password,
                     receipt_show_cashier, receipt_show_table, receipt_show_member, receipt_custom_logo_url)
                VALUES (true, 11, 'PPN', false, false, 0, 'Service Charge', 0,
                        true, '', '', true, false, '', '', true, true, true, '')
            ");
        }
    }

    private function ensureMenuTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.kategori_menu (
                id SERIAL PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                deskripsi TEXT,
                urutan INTEGER DEFAULT 0,
                station_id INTEGER,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.menu (
                id SERIAL PRIMARY KEY,
                kode VARCHAR(50) UNIQUE NOT NULL,
                nama VARCHAR(200) NOT NULL,
                kategori_id INTEGER REFERENCES {$schema}.kategori_menu(id),
                deskripsi TEXT,
                harga_jual DECIMAL(15,2) NOT NULL DEFAULT 0,
                harga_modal DECIMAL(15,2) DEFAULT 0,
                apply_fixed_cost BOOLEAN DEFAULT true,
                gambar_url VARCHAR(255),
                is_available BOOLEAN DEFAULT true,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.menu_bahan_baku (
                id SERIAL PRIMARY KEY,
                menu_id INTEGER REFERENCES {$schema}.menu(id) ON DELETE CASCADE,
                bahan_baku_id INTEGER,
                satuan_id INTEGER,
                jumlah DECIMAL(10,4) NOT NULL,
                keterangan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_menu_kategori ON {$schema}.menu(kategori_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_menu_bahan_menu ON {$schema}.menu_bahan_baku(menu_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_menu_bahan_baku ON {$schema}.menu_bahan_baku(bahan_baku_id)");
    }

    private function ensureBahanBakuTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.kategori_bahan_baku (
                id SERIAL PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                deskripsi TEXT,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.satuan (
                id SERIAL PRIMARY KEY,
                nama VARCHAR(50) NOT NULL,
                singkatan VARCHAR(10) NOT NULL,
                tipe VARCHAR(20) NOT NULL,
                is_base_unit BOOLEAN DEFAULT false,
                conversion_to_base DECIMAL(10,4),
                deskripsi TEXT,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.supplier (
                id SERIAL PRIMARY KEY,
                kode VARCHAR(50) UNIQUE NOT NULL,
                nama VARCHAR(200) NOT NULL,
                contact_person VARCHAR(100),
                phone VARCHAR(50),
                email VARCHAR(100),
                alamat TEXT,
                kota VARCHAR(100),
                provinsi VARCHAR(100),
                kode_pos VARCHAR(10),
                payment_terms VARCHAR(50),
                notes TEXT,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.bahan_baku (
                id SERIAL PRIMARY KEY,
                kode VARCHAR(50) UNIQUE NOT NULL,
                nama VARCHAR(200) NOT NULL,
                kategori_id INTEGER REFERENCES {$schema}.kategori_bahan_baku(id),
                satuan_id INTEGER REFERENCES {$schema}.satuan(id),
                satuan_pembelian_id INTEGER REFERENCES {$schema}.satuan(id),
                jumlah_per_unit_pembelian DECIMAL(10,4) DEFAULT 1,
                supplier_id INTEGER REFERENCES {$schema}.supplier(id),
                harga_beli DECIMAL(15,2) DEFAULT 0,
                minimum_stock DECIMAL(10,2) DEFAULT 0,
                current_stock DECIMAL(10,2) DEFAULT 0,
                lokasi_penyimpanan VARCHAR(100),
                expired_date DATE,
                gambar_url VARCHAR(255),
                deskripsi TEXT,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");
        // Compatibility ADD COLUMN IF NOT EXISTS for outlets that pre-existed.
        DB::statement("ALTER TABLE {$schema}.bahan_baku ADD COLUMN IF NOT EXISTS satuan_pembelian_id INTEGER REFERENCES {$schema}.satuan(id)");
        DB::statement("ALTER TABLE {$schema}.bahan_baku ADD COLUMN IF NOT EXISTS jumlah_per_unit_pembelian DECIMAL(10,4)");

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.stock_history (
                id SERIAL PRIMARY KEY,
                bahan_baku_id INTEGER REFERENCES {$schema}.bahan_baku(id),
                tipe VARCHAR(20) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL,
                stock_before DECIMAL(10,2) NOT NULL,
                stock_after DECIMAL(10,2) NOT NULL,
                reference_type VARCHAR(50),
                reference_id INTEGER,
                notes TEXT,
                created_by INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_bahan_baku_kategori ON {$schema}.bahan_baku(kategori_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_bahan_baku_supplier ON {$schema}.bahan_baku(supplier_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_bahan_baku_kode ON {$schema}.bahan_baku(kode)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_stock_history_bahan ON {$schema}.stock_history(bahan_baku_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_supplier_kode ON {$schema}.supplier(kode)");
    }

    private function ensureStationTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.stations (
                id SERIAL PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                deskripsi TEXT,
                warna VARCHAR(20) DEFAULT '#3b82f6',
                icon VARCHAR(50) DEFAULT 'pi pi-box',
                is_active BOOLEAN DEFAULT true,
                urutan INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL
            )
        ");

        if (!$this->columnExists($schema, 'menu', 'station_id')) {
            DB::statement("ALTER TABLE {$schema}.menu ADD COLUMN station_id INTEGER REFERENCES {$schema}.stations(id) ON DELETE SET NULL");
        }
        // station_id on kategori_menu — menus inherit station from their category
        DB::statement("ALTER TABLE {$schema}.kategori_menu ADD COLUMN IF NOT EXISTS station_id INTEGER REFERENCES {$schema}.stations(id) ON DELETE SET NULL");
        if (!$this->columnExists($schema, 'order_items', 'status')) {
            DB::statement("ALTER TABLE {$schema}.order_items ADD COLUMN status VARCHAR(20) DEFAULT 'pending'");
            DB::statement("ALTER TABLE {$schema}.order_items ADD COLUMN confirmed_at TIMESTAMP NULL");
            DB::statement("ALTER TABLE {$schema}.order_items ADD COLUMN confirmed_by BIGINT NULL");
        }
        // KDS timeline columns — added after initial station release
        DB::statement("ALTER TABLE {$schema}.order_items ADD COLUMN IF NOT EXISTS preparing_at TIMESTAMP NULL");
        DB::statement("ALTER TABLE {$schema}.order_items ADD COLUMN IF NOT EXISTS ready_at TIMESTAMP NULL");
        DB::statement("ALTER TABLE {$schema}.order_items ADD COLUMN IF NOT EXISTS served_at TIMESTAMP NULL");
        if (!$this->columnExists($schema, 'orders', 'kitchen_status')) {
            DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN kitchen_status VARCHAR(20) DEFAULT 'pending'");
        }
        DB::statement("CREATE INDEX IF NOT EXISTS idx_stations_active ON {$schema}.stations(is_active)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_menu_station ON {$schema}.menu(station_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_order_items_status ON {$schema}.order_items(status)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_orders_kitchen_status ON {$schema}.orders(kitchen_status)");

        DB::statement("SET search_path TO {$schema}, public");
        $count = DB::table('stations')->whereNull('deleted_at')->count();
        if ($count == 0) {
            DB::statement("
                INSERT INTO {$schema}.stations (nama, deskripsi, warna, icon, urutan) VALUES
                ('Kitchen', 'Dapur - makanan', '#ef4444', 'pi pi-fire', 1),
                ('Bar', 'Bar - minuman', '#3b82f6', 'pi pi-glass-cocktail', 2)
            ");
        }
        DB::statement('SET search_path TO public');
    }

    private function ensureStockOpnameTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;

        // Ensure locations + bahan_baku_locations + stock_movements exist so opname
        // can operate per-location even when LocationController has not been called yet.
        $this->ensureStockLocationTables($outlet);

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.stock_opname (
                id SERIAL PRIMARY KEY,
                kode VARCHAR(50) UNIQUE NOT NULL,
                tanggal_mulai DATE NOT NULL,
                tanggal_selesai DATE NOT NULL,
                status VARCHAR(20) DEFAULT 'draft',
                pic_name VARCHAR(100),
                pic_user_id INTEGER,
                notes TEXT,
                total_items INTEGER DEFAULT 0,
                total_difference_value DECIMAL(15,2) DEFAULT 0,
                approved_by INTEGER,
                approved_at TIMESTAMP,
                approval_notes TEXT,
                created_by INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.stock_opname_detail (
                id SERIAL PRIMARY KEY,
                stock_opname_id INTEGER NOT NULL,
                bahan_baku_id INTEGER NOT NULL REFERENCES {$schema}.bahan_baku(id) ON DELETE CASCADE,
                stock_location_id BIGINT,
                system_stock DECIMAL(10,2) NOT NULL,
                physical_stock DECIMAL(10,2),
                difference DECIMAL(10,2),
                difference_value DECIMAL(15,2),
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        // Backfill column for outlets provisioned before location-aware opname
        DB::statement("ALTER TABLE {$schema}.stock_opname_detail ADD COLUMN IF NOT EXISTS stock_location_id BIGINT");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_stock_opname_status ON {$schema}.stock_opname(status)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_stock_opname_tanggal ON {$schema}.stock_opname(tanggal_mulai, tanggal_selesai)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_stock_opname_detail_opname ON {$schema}.stock_opname_detail(stock_opname_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_stock_opname_detail_location ON {$schema}.stock_opname_detail(stock_location_id)");
    }

    /**
     * Ensure stock-location related tables (locations, bahan_baku_locations,
     * stock_movements) exist. Mirrors LocationController::ensureTables() but is
     * called at provisioning time so opname can safely reference them.
     */
    private function ensureStockLocationTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.locations (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                type VARCHAR(30) NOT NULL DEFAULT 'warehouse',
                description TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                display_order INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW(),
                deleted_at TIMESTAMP NULL
            )
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.bahan_baku_locations (
                id BIGSERIAL PRIMARY KEY,
                bahan_baku_id BIGINT NOT NULL,
                location_id BIGINT NOT NULL,
                current_stock DECIMAL(12,4) DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW(),
                UNIQUE(bahan_baku_id, location_id)
            )
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.stock_movements (
                id BIGSERIAL PRIMARY KEY,
                bahan_baku_id BIGINT NOT NULL,
                from_location_id BIGINT NULL,
                to_location_id BIGINT NULL,
                type VARCHAR(30) NOT NULL,
                quantity DECIMAL(12,4) NOT NULL,
                notes TEXT,
                reference_type VARCHAR(50),
                reference_id BIGINT,
                created_by BIGINT,
                created_at TIMESTAMP DEFAULT NOW()
            )
        ");
    }

    private function ensurePromoTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.promos (
                id SERIAL PRIMARY KEY,
                kode VARCHAR(50) UNIQUE NOT NULL,
                nama VARCHAR(100) NOT NULL,
                deskripsi TEXT,
                tipe VARCHAR(20) NOT NULL CHECK (tipe IN ('percentage', 'nominal')),
                nilai DECIMAL(10,2) NOT NULL,
                minimum_pembelian DECIMAL(10,2) DEFAULT 0,
                maksimum_diskon DECIMAL(10,2),
                tanggal_mulai DATE NOT NULL,
                tanggal_selesai DATE NOT NULL,
                jam_mulai TIME,
                jam_selesai TIME,
                hari_aktif VARCHAR(50),
                kuota_penggunaan INTEGER,
                jumlah_terpakai INTEGER DEFAULT 0,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");
        if (!$this->columnExists($schema, 'promos', 'is_stackable')) {
            DB::statement("ALTER TABLE {$schema}.promos ADD COLUMN is_stackable BOOLEAN DEFAULT false");
        }
        DB::statement("CREATE INDEX IF NOT EXISTS idx_promos_kode ON {$schema}.promos(kode)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_promos_active ON {$schema}.promos(is_active)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_promos_dates ON {$schema}.promos(tanggal_mulai, tanggal_selesai)");
    }

    private function ensureMembershipTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.members (
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
        $settingsExisted = $this->tableExists($schema, 'membership_settings');
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.membership_settings (
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
                registration_open BOOLEAN DEFAULT false,
                page_title VARCHAR(200) DEFAULT 'Daftar Member',
                page_description TEXT DEFAULT '',
                benefits JSONB DEFAULT '[]'::jsonb,
                welcome_message TEXT DEFAULT 'Selamat datang di program member kami!',
                require_phone BOOLEAN DEFAULT true,
                require_address BOOLEAN DEFAULT false,
                auto_approve BOOLEAN DEFAULT true,
                custom_primary_color VARCHAR(20) DEFAULT '',
                custom_logo_url TEXT DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        // Backfill public-page columns for outlets where the table already existed.
        if ($settingsExisted) {
            $publicColumns = [
                'registration_open'    => 'BOOLEAN DEFAULT false',
                'page_title'           => "VARCHAR(200) DEFAULT 'Daftar Member'",
                'page_description'     => "TEXT DEFAULT ''",
                'benefits'             => "JSONB DEFAULT '[]'::jsonb",
                'welcome_message'      => "TEXT DEFAULT 'Selamat datang di program member kami!'",
                'require_phone'        => 'BOOLEAN DEFAULT true',
                'require_address'      => 'BOOLEAN DEFAULT false',
                'auto_approve'         => 'BOOLEAN DEFAULT true',
                'custom_primary_color' => "VARCHAR(20) DEFAULT ''",
                'custom_logo_url'      => "TEXT DEFAULT ''",
            ];
            foreach ($publicColumns as $col => $def) {
                if (!$this->columnExists($schema, 'membership_settings', $col)) {
                    DB::statement("ALTER TABLE {$schema}.membership_settings ADD COLUMN {$col} {$def}");
                }
            }
        }
        if (!$settingsExisted) {
            DB::statement("INSERT INTO {$schema}.membership_settings (point_conversion_rate, point_per_rupiah, min_transaction_for_points) VALUES (1000, 1.00, 0)");
        }
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.point_transactions (
                id SERIAL PRIMARY KEY,
                member_id INTEGER NOT NULL REFERENCES {$schema}.members(id) ON DELETE CASCADE,
                type VARCHAR(20) NOT NULL,
                amount INTEGER NOT NULL,
                description TEXT,
                order_id INTEGER,
                balance_before INTEGER DEFAULT 0,
                balance_after INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        // Order/promo membership compatibility columns
        if (!$this->columnExists($schema, 'orders', 'member_id')) {
            DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN member_id INTEGER");
        }
        if (!$this->columnExists($schema, 'orders', 'points_earned')) {
            DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN points_earned INTEGER DEFAULT 0");
        }
        if (!$this->columnExists($schema, 'orders', 'points_redeemed')) {
            DB::statement("ALTER TABLE {$schema}.orders ADD COLUMN points_redeemed INTEGER DEFAULT 0");
        }
        if (!$this->columnExists($schema, 'promos', 'is_member_only')) {
            DB::statement("ALTER TABLE {$schema}.promos ADD COLUMN is_member_only BOOLEAN DEFAULT FALSE");
        }
    }

    private function ensureHrTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.employee_info (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                employee_code VARCHAR(50) UNIQUE,
                join_date DATE NOT NULL,
                employment_type VARCHAR(20) DEFAULT 'full_time',
                basic_salary DECIMAL(15,2) DEFAULT 0,
                hourly_rate DECIMAL(10,2) DEFAULT 0,
                overtime_rate DECIMAL(10,2) DEFAULT 0,
                bank_name VARCHAR(100),
                bank_account VARCHAR(50),
                bank_account_name VARCHAR(100),
                emergency_contact_name VARCHAR(100),
                emergency_contact_phone VARCHAR(20),
                address TEXT,
                day_off INTEGER DEFAULT 0,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("ALTER TABLE {$schema}.employee_info ADD COLUMN IF NOT EXISTS day_off INTEGER DEFAULT 0");

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.attendances (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                date DATE NOT NULL,
                clock_in TIMESTAMP,
                clock_out TIMESTAMP,
                clock_in_photo TEXT,
                clock_out_photo TEXT,
                clock_in_location TEXT,
                clock_out_location TEXT,
                clock_in_notes TEXT,
                clock_out_notes TEXT,
                work_hours DECIMAL(5,2) DEFAULT 0,
                overtime_hours DECIMAL(5,2) DEFAULT 0,
                status VARCHAR(20) DEFAULT 'present',
                approved_by INTEGER REFERENCES {$schema}.outlet_users(id),
                approved_at TIMESTAMP,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, date)
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_attendances_user_date ON {$schema}.attendances(user_id, date)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_attendances_date ON {$schema}.attendances(date)");

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.leave_requests (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                leave_type VARCHAR(20) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                total_days INTEGER NOT NULL,
                reason TEXT NOT NULL,
                attachment VARCHAR(255),
                status VARCHAR(20) DEFAULT 'pending',
                reviewed_by INTEGER REFERENCES {$schema}.outlet_users(id),
                reviewed_at TIMESTAMP,
                review_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_leave_requests_user ON {$schema}.leave_requests(user_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_leave_requests_status ON {$schema}.leave_requests(status)");

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.leave_balances (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                year INTEGER NOT NULL,
                leave_type VARCHAR(20) NOT NULL,
                total_days INTEGER DEFAULT 0,
                used_days INTEGER DEFAULT 0,
                remaining_days INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, year, leave_type)
            )
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.payrolls (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                period_month INTEGER NOT NULL,
                period_year INTEGER NOT NULL,
                basic_salary DECIMAL(15,2) DEFAULT 0,
                overtime_pay DECIMAL(15,2) DEFAULT 0,
                allowances DECIMAL(15,2) DEFAULT 0,
                bonuses DECIMAL(15,2) DEFAULT 0,
                deductions DECIMAL(15,2) DEFAULT 0,
                gross_salary DECIMAL(15,2) DEFAULT 0,
                net_salary DECIMAL(15,2) DEFAULT 0,
                work_days INTEGER DEFAULT 0,
                present_days INTEGER DEFAULT 0,
                absent_days INTEGER DEFAULT 0,
                leave_days INTEGER DEFAULT 0,
                late_days INTEGER DEFAULT 0,
                overtime_hours DECIMAL(5,2) DEFAULT 0,
                status VARCHAR(20) DEFAULT 'draft',
                payment_date DATE,
                payment_method VARCHAR(50),
                notes TEXT,
                created_by INTEGER REFERENCES {$schema}.outlet_users(id),
                approved_by INTEGER REFERENCES {$schema}.outlet_users(id),
                approved_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, period_month, period_year)
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_payrolls_user ON {$schema}.payrolls(user_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_payrolls_period ON {$schema}.payrolls(period_year, period_month)");

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.payroll_details (
                id SERIAL PRIMARY KEY,
                payroll_id INTEGER NOT NULL REFERENCES {$schema}.payrolls(id) ON DELETE CASCADE,
                type VARCHAR(20) NOT NULL,
                description VARCHAR(255) NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $settingsExisted = $this->tableExists($schema, 'payroll_settings');
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.payroll_settings (
                id SERIAL PRIMARY KEY,
                work_days_per_month INTEGER DEFAULT 22,
                work_hours_per_day DECIMAL(4,1) DEFAULT 8.0,
                overtime_multiplier DECIMAL(3,1) DEFAULT 1.5,
                late_tolerance_minutes INTEGER DEFAULT 15,
                annual_leave_days INTEGER DEFAULT 12,
                sick_leave_days INTEGER DEFAULT 12,
                tax_percentage DECIMAL(5,2) DEFAULT 0,
                attendance_location_lat DECIMAL(10,8),
                attendance_location_lng DECIMAL(11,8),
                attendance_radius INTEGER DEFAULT 100,
                weekly_day_off_enabled BOOLEAN DEFAULT true,
                min_staff_per_role INTEGER DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("ALTER TABLE {$schema}.payroll_settings ADD COLUMN IF NOT EXISTS weekly_day_off_enabled BOOLEAN DEFAULT true");
        DB::statement("ALTER TABLE {$schema}.payroll_settings ADD COLUMN IF NOT EXISTS min_staff_per_role INTEGER DEFAULT 1");
        if (!$settingsExisted) {
            DB::statement("
                INSERT INTO {$schema}.payroll_settings (work_days_per_month, work_hours_per_day, overtime_multiplier, late_tolerance_minutes, annual_leave_days, sick_leave_days, tax_percentage, attendance_radius)
                VALUES (22, 8.0, 1.5, 15, 12, 12, 0, 100)
            ");
        }
    }

    private function ensureShiftTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.shifts (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(20) NOT NULL UNIQUE,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                color VARCHAR(7) DEFAULT '#3b82f6',
                description TEXT,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.shift_assignments (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                shift_id INTEGER NOT NULL REFERENCES {$schema}.shifts(id) ON DELETE CASCADE,
                date DATE NOT NULL,
                status VARCHAR(20) DEFAULT 'scheduled',
                notes TEXT,
                created_by INTEGER REFERENCES {$schema}.outlet_users(id),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, date)
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_shift_assignments_date ON {$schema}.shift_assignments(date)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_shift_assignments_user ON {$schema}.shift_assignments(user_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_shift_assignments_shift ON {$schema}.shift_assignments(shift_id)");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.shift_swap_requests (
                id SERIAL PRIMARY KEY,
                requester_id INTEGER NOT NULL REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                requester_assignment_id INTEGER NOT NULL REFERENCES {$schema}.shift_assignments(id) ON DELETE CASCADE,
                target_id INTEGER REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                target_assignment_id INTEGER REFERENCES {$schema}.shift_assignments(id) ON DELETE CASCADE,
                reason TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                reviewed_by INTEGER REFERENCES {$schema}.outlet_users(id),
                reviewed_at TIMESTAMP,
                review_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.shift_templates (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.shift_template_details (
                id SERIAL PRIMARY KEY,
                template_id INTEGER NOT NULL REFERENCES {$schema}.shift_templates(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                shift_id INTEGER NOT NULL REFERENCES {$schema}.shifts(id) ON DELETE CASCADE,
                day_of_week INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function ensureKasbonTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        $existed = $this->tableExists($schema, 'kasbon_settings');
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.kasbon_settings (
                id SERIAL PRIMARY KEY,
                max_percentage DECIMAL(5,2) DEFAULT 50.00,
                require_approval BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        if (!$existed) {
            DB::statement("INSERT INTO {$schema}.kasbon_settings (max_percentage, require_approval) VALUES (50.00, true)");
        }
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.kasbon (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                request_date DATE NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                reason TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                approved_by INTEGER,
                approved_at TIMESTAMP,
                approval_proof TEXT,
                rejection_reason TEXT,
                repayment_status VARCHAR(20) DEFAULT 'unpaid',
                repayment_amount DECIMAL(15,2) DEFAULT 0,
                repayment_date DATE,
                repayment_proof TEXT,
                notes TEXT,
                created_by INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("ALTER TABLE {$schema}.kasbon ADD COLUMN IF NOT EXISTS approval_proof TEXT");
        DB::statement("ALTER TABLE {$schema}.kasbon ADD COLUMN IF NOT EXISTS repayment_proof TEXT");
    }

    private function ensurePurchaseExpenseTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.purchases (
                id SERIAL PRIMARY KEY,
                purchase_code VARCHAR(50) UNIQUE NOT NULL,
                supplier_id INTEGER,
                supplier_name VARCHAR(255),
                purchase_date DATE NOT NULL,
                total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                payment_method VARCHAR(50),
                payment_proof_url TEXT,
                notes TEXT,
                status VARCHAR(20) DEFAULT 'completed',
                created_by INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.purchase_items (
                id SERIAL PRIMARY KEY,
                purchase_id INTEGER NOT NULL REFERENCES {$schema}.purchases(id) ON DELETE CASCADE,
                bahan_baku_id INTEGER NOT NULL REFERENCES {$schema}.bahan_baku(id) ON DELETE RESTRICT,
                quantity DECIMAL(10,2) NOT NULL,
                unit_price DECIMAL(15,2) NOT NULL,
                subtotal DECIMAL(15,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.expenses (
                id SERIAL PRIMARY KEY,
                expense_code VARCHAR(50) UNIQUE NOT NULL,
                expense_date DATE NOT NULL,
                category VARCHAR(100) NOT NULL,
                description TEXT NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                payment_method VARCHAR(50),
                payment_proof_url TEXT,
                notes TEXT,
                created_by INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function ensureEmployeeBeverageTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        $existed = $this->tableExists($schema, 'employee_beverage_settings');
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.employee_beverage_settings (
                id SERIAL PRIMARY KEY,
                daily_quota INTEGER NOT NULL DEFAULT 1,
                is_active BOOLEAN DEFAULT true,
                reset_time TIME DEFAULT '00:00:00',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        if (!$existed) {
            DB::statement("
                INSERT INTO {$schema}.employee_beverage_settings (daily_quota, is_active, reset_time, notes)
                VALUES (1, true, '00:00:00', 'Default employee beverage allowance settings')
            ");
        }
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.employee_allowed_beverages (
                id SERIAL PRIMARY KEY,
                menu_id INTEGER NOT NULL REFERENCES {$schema}.menu(id) ON DELETE CASCADE,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.employee_beverage_claims (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES {$schema}.outlet_users(id) ON DELETE CASCADE,
                menu_id INTEGER NOT NULL REFERENCES {$schema}.menu(id) ON DELETE CASCADE,
                claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                claimed_date DATE DEFAULT CURRENT_DATE,
                notes TEXT,
                created_by INTEGER
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_beverage_claims_user_date ON {$schema}.employee_beverage_claims(user_id, claimed_date)");
    }

    /**
     * Production Unit tables — units, orders, order items.
     */
    private function ensureProductionTables(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.production_units (
                id BIGSERIAL PRIMARY KEY,
                nama VARCHAR(150) NOT NULL,
                deskripsi TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.production_orders (
                id BIGSERIAL PRIMARY KEY,
                unit_id BIGINT NOT NULL REFERENCES {$schema}.production_units(id),
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                notes TEXT,
                created_by BIGINT,
                completed_by BIGINT,
                completed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.production_order_items (
                id BIGSERIAL PRIMARY KEY,
                order_id BIGINT NOT NULL REFERENCES {$schema}.production_orders(id) ON DELETE CASCADE,
                bahan_baku_id BIGINT NOT NULL,
                quantity_planned DECIMAL(10,3) NOT NULL,
                quantity_actual DECIMAL(10,3),
                satuan_id BIGINT,
                location_id BIGINT,
                notes TEXT
            )
        ");

        DB::statement("CREATE INDEX IF NOT EXISTS idx_production_orders_unit ON {$schema}.production_orders(unit_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_production_orders_status ON {$schema}.production_orders(status)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_production_order_items_order ON {$schema}.production_order_items(order_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_production_order_items_bahan ON {$schema}.production_order_items(bahan_baku_id)");
    }

    /**
     * Cross-table FKs that have to wait until both sides exist. Idempotent.
     */
    private function ensureCrossTableFks(Outlet $outlet): void
    {
        $schema = $outlet->schema_name;
        $this->ensureFk($schema, 'order_items', 'fk_order_items_menu', 'menu_id', 'menu', 'id', 'RESTRICT');
        $this->ensureFk($schema, 'menu_bahan_baku', 'fk_menu_bahan_baku_bahan', 'bahan_baku_id', 'bahan_baku', 'id', null);
        $this->ensureFk($schema, 'menu_bahan_baku', 'fk_menu_bahan_baku_satuan', 'satuan_id', 'satuan', 'id', null);
        $this->ensureFk($schema, 'orders', 'fk_orders_member', 'member_id', 'members', 'id', 'SET NULL');
    }

    private function ensureFk(string $schema, string $table, string $constraint, string $column, string $refTable, string $refColumn, ?string $onDelete): void
    {
        if (!$this->tableExists($schema, $table) || !$this->tableExists($schema, $refTable)) {
            return;
        }
        if ($this->constraintExists($schema, $table, $constraint)) {
            return;
        }
        $clause = $onDelete ? " ON DELETE {$onDelete}" : '';
        DB::statement("ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$constraint} FOREIGN KEY ({$column}) REFERENCES {$schema}.{$refTable}({$refColumn}){$clause}");
    }

    private function tableExists(string $schema, string $table): bool
    {
        $row = DB::selectOne(
            'SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?) AS exists',
            [$schema, $table]
        );
        return (bool) ($row->exists ?? false);
    }

    private function columnExists(string $schema, string $table, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?) AS exists',
            [$schema, $table, $column]
        );
        return (bool) ($row->exists ?? false);
    }

    private function constraintExists(string $schema, string $table, string $constraint): bool
    {
        $row = DB::selectOne(
            'SELECT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ? AND constraint_name = ?) AS exists',
            [$schema, $table, $constraint]
        );
        return (bool) ($row->exists ?? false);
    }
}
