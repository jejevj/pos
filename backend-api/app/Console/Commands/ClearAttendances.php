<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearAttendances extends Command
{
    protected $signature = 'clear:attendances';
    protected $description = 'Clear all attendance records from all outlet schemas';

    public function handle()
    {
        $this->info('Clearing attendance records...');

        $outlets = DB::table('outlets')->get();

        foreach ($outlets as $outlet) {
            $this->info("Processing outlet: {$outlet->name} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                $count = DB::table('attendances')->count();
                DB::table('attendances')->delete();
                
                $this->info("  ✓ Deleted {$count} attendance records");
                
                DB::statement("SET search_path TO public");
            } catch (\Exception $e) {
                $this->error("  ✗ Error: " . $e->getMessage());
                DB::statement("SET search_path TO public");
            }
        }

        $this->info('All attendance records cleared!');
        return 0;
    }
}
