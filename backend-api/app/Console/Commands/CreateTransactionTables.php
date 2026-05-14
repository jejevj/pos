<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

class CreateTransactionTables extends Command
{
    protected $signature = 'outlets:create-transaction-tables';
    protected $description = 'Create transaction tables (tables, payment_methods, orders, order_items) in all outlet schemas';

    public function handle()
    {
        $outlets = Outlet::all();

        if ($outlets->isEmpty()) {
            $this->error('No outlets found!');
            return 1;
        }

        foreach ($outlets as $outlet) {
            $this->info("Creating transaction tables for outlet: {$outlet->name} (Schema: {$outlet->schema_name})");

            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");

                // Create tables table
                if (!$this->tableExists('tables')) {
                    DB::statement("
                        CREATE TABLE tables (
                            id BIGSERIAL PRIMARY KEY,
                            table_number VARCHAR(50) NOT NULL,
                            capacity INTEGER DEFAULT 4,
                            area VARCHAR(50) DEFAULT 'indoor',
                            status VARCHAR(20) DEFAULT 'available',
                            is_active BOOLEAN DEFAULT true,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            deleted_at TIMESTAMP NULL
                        )
                    ");
                    $this->info("  ✓ Created 'tables' table");
                } else {
                    $this->warn("  - 'tables' table already exists");
                }

                // Create payment_methods table
                if (!$this->tableExists('payment_methods')) {
                    DB::statement("
                        CREATE TABLE payment_methods (
                            id BIGSERIAL PRIMARY KEY,
                            name VARCHAR(100) NOT NULL,
                            code VARCHAR(50) NOT NULL,
                            icon VARCHAR(255) NULL,
                            is_active BOOLEAN DEFAULT true,
                            display_order INTEGER DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            deleted_at TIMESTAMP NULL
                        )
                    ");
                    $this->info("  ✓ Created 'payment_methods' table");
                    
                    // Insert default payment methods
                    DB::table('payment_methods')->insert([
                        ['name' => 'Cash', 'code' => 'cash', 'display_order' => 1, 'is_active' => true],
                        ['name' => 'Debit Card', 'code' => 'debit_card', 'display_order' => 2, 'is_active' => true],
                        ['name' => 'Credit Card', 'code' => 'credit_card', 'display_order' => 3, 'is_active' => true],
                        ['name' => 'E-Wallet', 'code' => 'e_wallet', 'display_order' => 4, 'is_active' => true],
                        ['name' => 'Transfer Bank', 'code' => 'bank_transfer', 'display_order' => 5, 'is_active' => true],
                        ['name' => 'QRIS', 'code' => 'qris', 'display_order' => 6, 'is_active' => true],
                    ]);
                    $this->info("  ✓ Inserted default payment methods");
                } else {
                    $this->warn("  - 'payment_methods' table already exists");
                }

                // Create orders table
                if (!$this->tableExists('orders')) {
                    DB::statement("
                        CREATE TABLE orders (
                            id BIGSERIAL PRIMARY KEY,
                            kode VARCHAR(50) UNIQUE NOT NULL,
                            order_type VARCHAR(20) NOT NULL,
                            table_id BIGINT NULL,
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
                            payment_method_id BIGINT NULL,
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
                            deleted_at TIMESTAMP NULL,
                            FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL,
                            FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL
                        )
                    ");
                    $this->info("  ✓ Created 'orders' table");
                } else {
                    $this->warn("  - 'orders' table already exists");
                }

                // Create order_items table.
                // Defer the FK to menu(id) so this command can be run before
                // outlets:create-menu-tables. The FK is attached afterwards
                // (idempotently) when the menu table exists.
                if (!$this->tableExists('order_items')) {
                    DB::statement("
                        CREATE TABLE order_items (
                            id BIGSERIAL PRIMARY KEY,
                            order_id BIGINT NOT NULL,
                            menu_id BIGINT NOT NULL,
                            menu_name VARCHAR(255) NOT NULL,
                            menu_price DECIMAL(15,2) NOT NULL,
                            quantity INTEGER NOT NULL,
                            subtotal DECIMAL(15,2) NOT NULL,
                            notes TEXT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
                        )
                    ");
                    $this->info("  ✓ Created 'order_items' table");
                } else {
                    $this->warn("  - 'order_items' table already exists");
                }

                // Attach FK from order_items.menu_id -> menu(id) when menu exists.
                $this->ensureMenuForeignKey($outlet->schema_name);

                // Insert sample tables
                if ($this->tableExists('tables') && DB::table('tables')->count() == 0) {
                    $sampleTables = [];
                    for ($i = 1; $i <= 10; $i++) {
                        $sampleTables[] = [
                            'table_number' => (string)$i,
                            'capacity' => $i <= 5 ? 4 : 6,
                            'area' => $i <= 7 ? 'indoor' : 'outdoor',
                            'status' => 'available',
                            'is_active' => true,
                        ];
                    }
                    DB::table('tables')->insert($sampleTables);
                    $this->info("  ✓ Inserted 10 sample tables");
                }

                DB::statement("SET search_path TO public");
                $this->info("✓ Successfully created transaction tables for {$outlet->name}\n");

            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->error("✗ Error creating tables for {$outlet->name}: {$e->getMessage()}\n");
            }
        }

        $this->info('Transaction tables creation completed!');
        return 0;
    }

    private function tableExists($tableName)
    {
        $result = DB::select("
            SELECT EXISTS (
                SELECT FROM information_schema.tables
                WHERE table_schema = current_schema()
                AND table_name = ?
            )
        ", [$tableName]);

        return $result[0]->exists;
    }

    private function tableExistsInSchema($schema, $table)
    {
        $row = DB::selectOne(
            "SELECT EXISTS (
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = ? AND table_name = ?
            ) AS exists",
            [$schema, $table]
        );
        return (bool) ($row->exists ?? false);
    }

    private function constraintExists($schema, $table, $constraint)
    {
        $row = DB::selectOne(
            "SELECT EXISTS (
                SELECT 1 FROM information_schema.table_constraints
                WHERE table_schema = ? AND table_name = ? AND constraint_name = ?
            ) AS exists",
            [$schema, $table, $constraint]
        );
        return (bool) ($row->exists ?? false);
    }

    private function ensureMenuForeignKey($schema)
    {
        if (! $this->tableExistsInSchema($schema, 'order_items')) {
            return;
        }
        if (! $this->tableExistsInSchema($schema, 'menu')) {
            $this->warn("  - Skipping FK order_items -> menu: {$schema}.menu does not exist yet. Run outlets:create-menu-tables, then re-run this command.");
            return;
        }
        $constraint = 'fk_order_items_menu';
        if ($this->constraintExists($schema, 'order_items', $constraint)) {
            return;
        }
        DB::statement(
            "ALTER TABLE {$schema}.order_items ADD CONSTRAINT {$constraint} " .
            "FOREIGN KEY (menu_id) REFERENCES {$schema}.menu(id) ON DELETE RESTRICT"
        );
        $this->info("  ✓ Added FK order_items.menu_id -> menu(id)");
    }
}
