<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AddLocationToOutlets extends Command
{
    protected $signature = 'outlets:add-location';
    protected $description = 'Add latitude and longitude columns to outlets table';

    public function handle()
    {
        try {
            // Check if columns already exist
            $hasLatitude = DB::select("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_schema = 'public' 
                AND table_name = 'outlets' 
                AND column_name = 'latitude'
            ");

            if (empty($hasLatitude)) {
                DB::statement("
                    ALTER TABLE public.outlets 
                    ADD COLUMN latitude DECIMAL(10, 8),
                    ADD COLUMN longitude DECIMAL(11, 8)
                ");
                
                $this->info('✓ Added latitude and longitude columns to outlets table');
            } else {
                $this->info('Latitude and longitude columns already exist');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
