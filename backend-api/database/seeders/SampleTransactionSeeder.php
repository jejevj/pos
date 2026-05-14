<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SampleTransactionSeeder extends Seeder
{
    public function run(): void
    {
        // Use specific schema
        $schemaName = 'user_1_outlet_baru';
        
        $this->command->info("🔍 Using schema: {$schemaName}");
        
        // Get outlet info
        $outlet = DB::table('outlets')->where('schema_name', $schemaName)->first();
        
        if (!$outlet) {
            $this->command->error("❌ Outlet with schema {$schemaName} not found.");
            return;
        }
        
        $this->command->info("✅ Found outlet: {$outlet->name} (ID: {$outlet->id})");
        
        // Switch to outlet schema
        DB::statement("SET search_path TO {$schemaName}, public");
        
        // Check if stations exist
        $kitchenStation = DB::table('stations')->where('nama', 'Kitchen')->first();
        $barStation = DB::table('stations')->where('nama', 'Bar')->first();
        
        if (!$kitchenStation || !$barStation) {
            $this->command->error('❌ Kitchen or Bar station not found.');
            DB::statement("SET search_path TO public");
            return;
        }
        
        $this->command->info("✅ Found stations: Kitchen (ID: {$kitchenStation->id}), Bar (ID: {$barStation->id})");
        
        // Check if menus exist, if not create sample menus
        $menuCount = DB::table('menu')->where('is_active', true)->count();
        
        if ($menuCount < 5) {
            $this->command->info('📝 Creating sample menus...');
            
            // Kitchen menus
            DB::table('menu')->insert([
                [
                    'kode' => 'MN-' . strtoupper(substr(md5('nasi-goreng'), 0, 6)),
                    'nama' => 'Nasi Goreng Spesial',
                    'harga_jual' => 25000,
                    'is_active' => true,
                    'station_id' => $kitchenStation->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'kode' => 'MN-' . strtoupper(substr(md5('mie-goreng'), 0, 6)),
                    'nama' => 'Mie Goreng',
                    'harga_jual' => 20000,
                    'is_active' => true,
                    'station_id' => $kitchenStation->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'kode' => 'MN-' . strtoupper(substr(md5('ayam-bakar'), 0, 6)),
                    'nama' => 'Ayam Bakar',
                    'harga_jual' => 30000,
                    'is_active' => true,
                    'station_id' => $kitchenStation->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            
            // Bar menus
            DB::table('menu')->insert([
                [
                    'kode' => 'MN-' . strtoupper(substr(md5('es-teh'), 0, 6)),
                    'nama' => 'Es Teh Manis',
                    'harga_jual' => 5000,
                    'is_active' => true,
                    'station_id' => $barStation->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'kode' => 'MN-' . strtoupper(substr(md5('jus-jeruk'), 0, 6)),
                    'nama' => 'Jus Jeruk',
                    'harga_jual' => 12000,
                    'is_active' => true,
                    'station_id' => $barStation->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            
            $this->command->info('✅ Sample menus created');
        }
        
        // Get menus from each station
        $kitchenMenus = DB::table('menu')->where('station_id', $kitchenStation->id)->where('is_active', true)->limit(3)->get();
        $barMenus = DB::table('menu')->where('station_id', $barStation->id)->where('is_active', true)->limit(2)->get();
        
        // If not enough menus assigned, assign some
        if ($kitchenMenus->count() < 3) {
            $this->command->info('📝 Assigning menus to Kitchen station...');
            $unassignedMenus = DB::table('menu')
                ->where('is_active', true)
                ->whereNull('station_id')
                ->orWhere('station_id', '!=', $barStation->id)
                ->limit(3)
                ->get();
            
            foreach ($unassignedMenus as $menu) {
                DB::table('menu')->where('id', $menu->id)->update(['station_id' => $kitchenStation->id]);
            }
            
            $kitchenMenus = DB::table('menu')->where('station_id', $kitchenStation->id)->where('is_active', true)->limit(3)->get();
        }
        
        if ($barMenus->count() < 2) {
            $this->command->info('📝 Assigning menus to Bar station...');
            $unassignedMenus = DB::table('menu')
                ->where('is_active', true)
                ->whereNull('station_id')
                ->orWhere('station_id', '!=', $kitchenStation->id)
                ->limit(2)
                ->get();
            
            foreach ($unassignedMenus as $menu) {
                DB::table('menu')->where('id', $menu->id)->update(['station_id' => $barStation->id]);
            }
            
            $barMenus = DB::table('menu')->where('station_id', $barStation->id)->where('is_active', true)->limit(2)->get();
        }
        
        if ($kitchenMenus->isEmpty() || $barMenus->isEmpty()) {
            $this->command->error('❌ Not enough menus found in outlet. Please create some menus first.');
            DB::statement("SET search_path TO public");
            return;
        }
        
        $this->command->info("✅ Found {$kitchenMenus->count()} kitchen menus and {$barMenus->count()} bar menus");
        
        // Get payment method
        $paymentMethod = DB::table('payment_methods')->where('is_active', true)->first();
        if (!$paymentMethod) {
            $this->command->error('❌ No payment method found.');
            DB::statement("SET search_path TO public");
            return;
        }
        
        // Get user for cashier_id (from public schema)
        DB::statement("SET search_path TO public");
        $user = DB::table('users')->first();
        DB::statement("SET search_path TO {$schemaName}, public");
        
        if (!$user) {
            $this->command->error('❌ No user found for cashier.');
            DB::statement("SET search_path TO public");
            return;
        }
        
        $this->command->info('🚀 Creating sample transaction...');
        
        // Timeline setup (realistic timing)
        $orderCreated = Carbon::now()->subMinutes(15); // Order created 15 minutes ago
        
        // Kitchen timeline (3 items)
        $kitchenStartPrep = $orderCreated->copy()->addSeconds(30); // 30s delay to kitchen
        $kitchenItem1Ready = $kitchenStartPrep->copy()->addMinutes(3)->addSeconds(30); // 3.5m prep
        $kitchenItem2Ready = $kitchenStartPrep->copy()->addMinutes(4); // 4m prep
        $kitchenItem3Ready = $kitchenStartPrep->copy()->addMinutes(5); // 5m prep (slowest)
        
        // Bar timeline (2 items) - starts at same time but faster
        $barStartPrep = $orderCreated->copy()->addSeconds(30); // Same 30s delay
        $barItem1Ready = $barStartPrep->copy()->addMinutes(2); // 2m prep
        $barItem2Ready = $barStartPrep->copy()->addMinutes(3); // 3m prep
        
        // All items ready at 5.5 minutes (kitchen item 3 is last)
        $allItemsReady = $kitchenItem3Ready;
        
        // Served to customer 1 minute after all ready
        $servedToCustomer = $allItemsReady->copy()->addMinute();
        
        // Create order
        $orderId = DB::table('orders')->insertGetId([
            'kode' => 'ORD-' . strtoupper(substr(md5(time()), 0, 8)),
            'order_type' => 'dine_in',
            'table_id' => DB::table('tables')->where('status', 'available')->first()?->id,
            'table_number' => 'T-05',
            'customer_name' => 'John Doe',
            'customer_phone' => '081234567890',
            'status' => 'paid',
            'subtotal' => 0,
            'tax_percentage' => 11,
            'tax_amount' => 0,
            'service_charge_percentage' => 0,
            'service_charge_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'payment_method_id' => $paymentMethod->id,
            'paid_amount' => 0,
            'change_amount' => 0,
            'cashier_id' => $user->id,
            'created_at' => $orderCreated,
            'updated_at' => $orderCreated,
            'paid_at' => $servedToCustomer->copy()->addSeconds(30),
        ]);
        
        $this->command->info("✅ Order created with ID: {$orderId}");
        
        // Create order items - Kitchen items
        $subtotal = 0;
        $itemNumber = 1;
        
        foreach ($kitchenMenus as $index => $menu) {
            $quantity = 1;
            $itemSubtotal = $menu->harga_jual * $quantity;
            $subtotal += $itemSubtotal;
            
            $preparingAt = $kitchenStartPrep;
            $readyAt = match($index) {
                0 => $kitchenItem1Ready,
                1 => $kitchenItem2Ready,
                default => $kitchenItem3Ready,
            };
            $servedAt = $servedToCustomer;
            
            $orderItemData = [
                'order_id' => $orderId,
                'menu_id' => $menu->id,
                'menu_name' => $menu->nama,
                'menu_price' => $menu->harga_jual,
                'quantity' => $quantity,
                'subtotal' => $itemSubtotal,
                'notes' => $index === 0 ? 'Extra spicy' : null,
                'status' => 'served',
                'preparing_at' => $preparingAt,
                'ready_at' => $readyAt,
                'served_at' => $servedAt,
                'created_at' => $orderCreated,
                'updated_at' => $servedAt,
            ];
            
            // Add station_id if column exists
            $stationIdExists = DB::selectOne("
                SELECT column_name FROM information_schema.columns 
                WHERE table_schema = ? AND table_name = 'order_items' AND column_name = 'station_id'
            ", [$schemaName]);
            
            if ($stationIdExists) {
                $orderItemData['station_id'] = $kitchenStation->id;
            }
            
            DB::table('order_items')->insert($orderItemData);
            
            $this->command->info("  ✅ Kitchen Item {$itemNumber}: {$menu->nama} - Prep: " . $preparingAt->diffInSeconds($readyAt) . "s");
            $itemNumber++;
        }
        
        // Create order items - Bar items
        foreach ($barMenus as $index => $menu) {
            $quantity = 1;
            $itemSubtotal = $menu->harga_jual * $quantity;
            $subtotal += $itemSubtotal;
            
            $preparingAt = $barStartPrep;
            $readyAt = match($index) {
                0 => $barItem1Ready,
                default => $barItem2Ready,
            };
            $servedAt = $servedToCustomer;
            
            $orderItemData = [
                'order_id' => $orderId,
                'menu_id' => $menu->id,
                'menu_name' => $menu->nama,
                'menu_price' => $menu->harga_jual,
                'quantity' => $quantity,
                'subtotal' => $itemSubtotal,
                'notes' => null,
                'status' => 'served',
                'preparing_at' => $preparingAt,
                'ready_at' => $readyAt,
                'served_at' => $servedAt,
                'created_at' => $orderCreated,
                'updated_at' => $servedAt,
            ];
            
            // Add station_id if column exists
            $stationIdExists = DB::selectOne("
                SELECT column_name FROM information_schema.columns 
                WHERE table_schema = ? AND table_name = 'order_items' AND column_name = 'station_id'
            ", [$schemaName]);
            
            if ($stationIdExists) {
                $orderItemData['station_id'] = $barStation->id;
            }
            
            DB::table('order_items')->insert($orderItemData);
            
            $this->command->info("  ✅ Bar Item {$itemNumber}: {$menu->nama} - Prep: " . $preparingAt->diffInSeconds($readyAt) . "s");
            $itemNumber++;
        }
        
        // Calculate totals
        $taxAmount = $subtotal * 0.11;
        $totalAmount = $subtotal + $taxAmount;
        $paidAmount = ceil($totalAmount / 1000) * 1000;
        $changeAmount = $paidAmount - $totalAmount;
        
        // Update order with totals
        DB::table('orders')->where('id', $orderId)->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'change_amount' => $changeAmount,
        ]);
        
        $this->command->info("✅ Order totals updated - Total: Rp " . number_format($totalAmount, 0, ',', '.'));
        
        // Calculate timing summary
        $orderToKitchen = $orderCreated->diffInSeconds($kitchenStartPrep);
        $kitchenProcessing = $kitchenStartPrep->diffInSeconds($kitchenItem3Ready);
        $barProcessing = $barStartPrep->diffInSeconds($barItem2Ready);
        $readyToServed = $allItemsReady->diffInSeconds($servedToCustomer);
        $totalTime = $orderCreated->diffInSeconds($servedToCustomer);
        
        $this->command->info('');
        $this->command->info('⏱️  TIMING BREAKDOWN:');
        $this->command->info("  1. Order → Kitchen Start: {$orderToKitchen}s (0.5m)");
        $this->command->info("  2. Kitchen Processing (3 items): {$kitchenProcessing}s (" . round($kitchenProcessing/60, 1) . "m)");
        $this->command->info("  3. Bar Processing (2 items): {$barProcessing}s (" . round($barProcessing/60, 1) . "m) [PARALLEL]");
        $this->command->info("  4. Ready → Served: {$readyToServed}s (1.0m)");
        $this->command->info("  ─────────────────────────────");
        $this->command->info("  📊 TOTAL END-TO-END: {$totalTime}s (" . round($totalTime/60, 1) . "m)");
        $this->command->info('');
        
        // Get order code before resetting schema
        $orderCode = DB::table('orders')->where('id', $orderId)->value('kode');
        
        // Reset schema
        DB::statement("SET search_path TO public");
        
        $this->command->info('✅ Sample transaction created successfully!');
        $this->command->info("📝 Order Code: {$orderCode}");
        $this->command->info("🏪 Outlet: {$outlet->name}");
        $this->command->info('');
        $this->command->info('💡 You can now view this transaction in the Transaction View to see:');
        $this->command->info('   - End-to-end time summary with color coding');
        $this->command->info('   - Parallel processing from Kitchen and Bar stations');
        $this->command->info('   - Detailed breakdown of each step with auto unit (s/m/h)');
    }
}
