<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

class AddAppliedPromosToOrders extends Command
{
    protected $signature = 'orders:add-applied-promos-column';
    protected $description = 'Add applied_promos JSON column to orders table in all outlet schemas';

    public function handle()
    {
        $outlets = Outlet::all();
        
        if ($outlets->isEmpty()) {
            $this->error('No outlets found.');
            return 1;
        }

        foreach ($outlets as $outlet) {
            $this->info("Updating orders table for outlet: {$outlet->name} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Check if column exists
                $columnExists = DB::select("
                    SELECT EXISTS (
                        SELECT FROM information_schema.columns 
                        WHERE table_schema = current_schema()
                        AND table_name = 'orders'
                        AND column_name = 'applied_promos'
                    )
                ")[0]->exists;
                
                if (!$columnExists) {
                    DB::statement("ALTER TABLE orders ADD COLUMN applied_promos JSONB DEFAULT '[]'::jsonb");
                    $this->info('  ✓ Added applied_promos column');
                } else {
                    $this->warn('  - applied_promos column already exists');
                }
                
                DB::statement("SET search_path TO public");
                
                $this->info("✓ Successfully updated {$outlet->name}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->error("Failed to update {$outlet->name}: " . $e->getMessage());
                return 1;
            }
        }

        $this->info('All orders tables updated successfully!');
        return 0;
    }
}
