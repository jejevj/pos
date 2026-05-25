<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds overtime-related columns to all outlet_* schema attendances tables.
     */
    public function up(): void
    {
        // Add to public schema attendances (if exists)
        $this->addColumnsIfNeeded('public', 'attendances');

        // Add to all outlet_* schemas
        $schemas = DB::select("
            SELECT schema_name
            FROM information_schema.schemata
            WHERE schema_name LIKE 'outlet_%'
        ");

        foreach ($schemas as $schema) {
            $schemaName = $schema->schema_name;
            $this->addColumnsIfNeeded($schemaName, 'attendances');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop from public schema
        $this->dropColumnsIfExists('public', 'attendances');

        // Drop from all outlet_* schemas
        $schemas = DB::select("
            SELECT schema_name
            FROM information_schema.schemata
            WHERE schema_name LIKE 'outlet_%'
        ");

        foreach ($schemas as $schema) {
            $schemaName = $schema->schema_name;
            $this->dropColumnsIfExists($schemaName, 'attendances');
        }
    }

    private function addColumnsIfNeeded(string $schema, string $table): void
    {
        $tableExists = DB::select("
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = ? AND table_name = ?
        ", [$schema, $table]);

        if (empty($tableExists)) {
            return;
        }

        $columns = [
            'overtime_reason'      => "ALTER TABLE \"{$schema}\".\"{$table}\" ADD COLUMN IF NOT EXISTS \"overtime_reason\" TEXT NULL",
            'overtime_status'      => "ALTER TABLE \"{$schema}\".\"{$table}\" ADD COLUMN IF NOT EXISTS \"overtime_status\" VARCHAR(20) NULL DEFAULT 'pending_approval'",
            'overtime_approved_by' => "ALTER TABLE \"{$schema}\".\"{$table}\" ADD COLUMN IF NOT EXISTS \"overtime_approved_by\" BIGINT NULL",
            'overtime_approved_at' => "ALTER TABLE \"{$schema}\".\"{$table}\" ADD COLUMN IF NOT EXISTS \"overtime_approved_at\" TIMESTAMP NULL",
            'overtime_notes'       => "ALTER TABLE \"{$schema}\".\"{$table}\" ADD COLUMN IF NOT EXISTS \"overtime_notes\" TEXT NULL",
        ];

        foreach ($columns as $column => $sql) {
            $exists = DB::select("
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = ? AND table_name = ? AND column_name = ?
            ", [$schema, $table, $column]);

            if (empty($exists)) {
                DB::statement($sql);
            }
        }
    }

    private function dropColumnsIfExists(string $schema, string $table): void
    {
        $tableExists = DB::select("
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = ? AND table_name = ?
        ", [$schema, $table]);

        if (empty($tableExists)) {
            return;
        }

        $columns = [
            'overtime_reason',
            'overtime_status',
            'overtime_approved_by',
            'overtime_approved_at',
            'overtime_notes',
        ];

        foreach ($columns as $column) {
            DB::statement("
                ALTER TABLE \"{$schema}\".\"{$table}\" DROP COLUMN IF EXISTS \"{$column}\"
            ");
        }
    }
};
