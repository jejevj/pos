<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AddOutletIdentityColumns extends Command
{
    protected $signature = 'outlets:add-identity-columns';
    protected $description = 'Add identity columns (logo, address, phone, etc) to outlets table';

    public function handle()
    {
        try {
            $columns = [
                'logo' => 'TEXT',
                'address' => 'TEXT',
                'phone' => 'VARCHAR(20)',
                'email' => 'VARCHAR(255)',
                'website' => 'VARCHAR(255)',
                'description' => 'TEXT',
                'business_hours' => 'TEXT',
                'social_media' => 'JSONB'
            ];

            $addedColumns = [];
            $existingColumns = [];

            foreach ($columns as $columnName => $columnType) {
                // Check if column exists
                $exists = DB::select("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_schema = 'public' 
                    AND table_name = 'outlets' 
                    AND column_name = ?
                ", [$columnName]);

                if (empty($exists)) {
                    // Add column
                    DB::statement("
                        ALTER TABLE public.outlets 
                        ADD COLUMN {$columnName} {$columnType}
                    ");
                    $addedColumns[] = $columnName;
                } else {
                    $existingColumns[] = $columnName;
                }
            }

            if (!empty($addedColumns)) {
                $this->info('✓ Added columns: ' . implode(', ', $addedColumns));
            }

            if (!empty($existingColumns)) {
                $this->info('Columns already exist: ' . implode(', ', $existingColumns));
            }

            if (empty($addedColumns) && empty($existingColumns)) {
                $this->info('No changes needed');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
