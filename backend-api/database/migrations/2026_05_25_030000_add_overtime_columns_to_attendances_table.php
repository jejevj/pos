<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ambil semua schema selain system schemas
        $schemas = DB::select("
            SELECT schema_name
            FROM information_schema.schemata
            WHERE schema_name NOT IN ('public', 'information_schema', 'pg_catalog', 'pg_toast')
              AND schema_name NOT LIKE 'pg_%'
        ");

        // Tambah juga public schema jika ada tabel attendances di sana
        array_unshift($schemas, (object)['schema_name' => 'public']);

        foreach ($schemas as $schema) {
            $s = $schema->schema_name;

            // Cek apakah tabel attendances ada di schema ini
            $tableExists = DB::selectOne("
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = ? AND table_name = 'attendances'
            ", [$s]);

            if (!$tableExists) {
                continue;
            }

            $columns = [
                'overtime_reason'      => "ALTER TABLE \"$s\".\"attendances\" ADD COLUMN IF NOT EXISTS overtime_reason TEXT NULL",
                'overtime_status'      => "ALTER TABLE \"$s\".\"attendances\" ADD COLUMN IF NOT EXISTS overtime_status VARCHAR(20) NULL DEFAULT 'pending_approval'",
                'overtime_approved_by' => "ALTER TABLE \"$s\".\"attendances\" ADD COLUMN IF NOT EXISTS overtime_approved_by BIGINT NULL",
                'overtime_approved_at' => "ALTER TABLE \"$s\".\"attendances\" ADD COLUMN IF NOT EXISTS overtime_approved_at TIMESTAMP NULL",
                'overtime_notes'       => "ALTER TABLE \"$s\".\"attendances\" ADD COLUMN IF NOT EXISTS overtime_notes TEXT NULL",
            ];

            foreach ($columns as $col => $sql) {
                $exists = DB::selectOne("
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = ? AND table_name = 'attendances' AND column_name = ?
                ", [$s, $col]);

                if (!$exists) {
                    DB::statement($sql);
                }
            }
        }
    }

    public function down(): void
    {
        $schemas = DB::select("
            SELECT schema_name
            FROM information_schema.schemata
            WHERE schema_name NOT IN ('information_schema', 'pg_catalog', 'pg_toast')
              AND schema_name NOT LIKE 'pg_%'
        ");

        foreach ($schemas as $schema) {
            $s = $schema->schema_name;

            $tableExists = DB::selectOne("
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = ? AND table_name = 'attendances'
            ", [$s]);

            if (!$tableExists) continue;

            foreach (['overtime_reason', 'overtime_status', 'overtime_approved_by', 'overtime_approved_at', 'overtime_notes'] as $col) {
                DB::statement("ALTER TABLE \"$s\".\"attendances\" DROP COLUMN IF EXISTS $col");
            }
        }
    }
};
