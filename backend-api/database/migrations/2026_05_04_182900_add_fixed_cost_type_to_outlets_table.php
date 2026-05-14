<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->string('fixed_cost_type', 20)->default('percentage')->after('fixed_cost_percentage')->comment('Type: percentage or nominal');
            $table->decimal('fixed_cost_nominal', 15, 2)->default(0)->after('fixed_cost_type')->comment('Fixed cost in nominal amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn(['fixed_cost_type', 'fixed_cost_nominal']);
        });
    }
};
