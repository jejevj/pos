<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class VerifyOutletSchemas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outlets:verify-schemas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that all outlet schemas exist in PostgreSQL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Verifying outlet schemas...');
        $this->newLine();
        
        // Get all schemas from PostgreSQL
        $pgSchemas = DB::select("
            SELECT schema_name 
            FROM information_schema.schemata 
            WHERE schema_name LIKE 'user_%'
        ");
        
        $this->info("PostgreSQL Schemas found:");
        foreach ($pgSchemas as $schema) {
            $this->line("  📁 {$schema->schema_name}");
            
            // Check if outlet_users table exists
            $tables = DB::select("
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = ? AND table_name = 'outlet_users'
            ", [$schema->schema_name]);
            
            if (!empty($tables)) {
                $this->info("    ✅ outlet_users table exists");
                
                // Count users in this schema
                DB::statement("SET search_path TO {$schema->schema_name}, public");
                $count = DB::table('outlet_users')->count();
                DB::statement("SET search_path TO public");
                
                $this->info("    👥 {$count} user(s) in this outlet");
            } else {
                $this->warn("    ❌ outlet_users table NOT found");
            }
        }
        
        $this->newLine();
        
        // Get all outlets from database
        $outlets = Outlet::all();
        
        $this->info("Outlets in database:");
        foreach ($outlets as $outlet) {
            $schemaExists = collect($pgSchemas)->contains('schema_name', $outlet->schema_name);
            
            if ($schemaExists) {
                $this->info("  ✅ {$outlet->name} → {$outlet->schema_name}");
            } else {
                $this->error("  ❌ {$outlet->name} → {$outlet->schema_name} (SCHEMA NOT FOUND!)");
            }
        }

        return 0;
    }
}
