<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Outlet;

class FixOutletSchemaNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outlets:fix-schema-names';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix outlet schema names by replacing dashes with underscores';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing outlet schema names...');
        
        $outlets = Outlet::all();
        
        if ($outlets->isEmpty()) {
            $this->warn('No outlets found.');
            return 0;
        }

        $fixed = 0;

        foreach ($outlets as $outlet) {
            $oldSchemaName = $outlet->schema_name;
            $newSchemaName = str_replace('-', '_', $oldSchemaName);
            
            if ($oldSchemaName !== $newSchemaName) {
                $outlet->schema_name = $newSchemaName;
                $outlet->save();
                
                $this->info("✅ Fixed: {$oldSchemaName} → {$newSchemaName}");
                $fixed++;
            } else {
                $this->line("⏭️  OK: {$oldSchemaName}");
            }
        }

        $this->newLine();
        $this->info("Fixed {$fixed} outlet(s).");

        return 0;
    }
}
