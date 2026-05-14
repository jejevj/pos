<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class CreateOutletSchemas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outlets:create-schemas {--force : Force recreate schemas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create PostgreSQL schemas for all outlets that don\'t have one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking outlets...');
        
        $outlets = Outlet::all();
        
        if ($outlets->isEmpty()) {
            $this->warn('No outlets found.');
            return 0;
        }

        $this->info("Found {$outlets->count()} outlet(s).");
        
        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($outlets as $outlet) {
            $schemaExists = $this->schemaExists($outlet->schema_name);
            
            if ($schemaExists && !$this->option('force')) {
                $this->line("⏭️  Skipping {$outlet->name} - schema already exists: {$outlet->schema_name}");
                $skipped++;
                continue;
            }

            if ($schemaExists && $this->option('force')) {
                $this->warn("🔄 Recreating schema for {$outlet->name}: {$outlet->schema_name}");
                $outlet->dropSchema();
            }

            $this->info("🔨 Creating schema for {$outlet->name}: {$outlet->schema_name}");
            
            if ($outlet->createSchema()) {
                $this->info("✅ Successfully created schema: {$outlet->schema_name}");
                $created++;
            } else {
                $this->error("❌ Failed to create schema: {$outlet->schema_name}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("✅ Created: {$created}");
        $this->info("⏭️  Skipped: {$skipped}");
        $this->info("❌ Failed: {$failed}");

        return 0;
    }

    /**
     * Check if schema exists
     */
    private function schemaExists($schemaName)
    {
        $result = DB::select("
            SELECT schema_name 
            FROM information_schema.schemata 
            WHERE schema_name = ?
        ", [$schemaName]);

        return !empty($result);
    }
}
