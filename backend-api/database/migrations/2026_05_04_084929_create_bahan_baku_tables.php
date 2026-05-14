<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * These tables will be created in each outlet's schema
     */
    public function up(): void
    {
        // This migration will be run manually for each outlet schema
        // via artisan command: php artisan outlets:create-bahan-baku-tables
        
        // We'll create a template here for documentation
        // Actual tables will be created in outlet schemas
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tables are in outlet schemas, will be dropped with schema
    }
};
