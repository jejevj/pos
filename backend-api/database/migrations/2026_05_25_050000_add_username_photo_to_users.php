<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah username di tabel users (public schema)
        $this->addColumnIfNotExists('public', 'users', 'username',
            "ALTER TABLE \"public\".\"users\" ADD COLUMN IF NOT EXISTS username VARCHAR(64) NULL"
        );
        // Buat unique index jika belum ada
        try {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS users_username_unique ON \"public\".\"users\" (username) WHERE username IS NOT NULL");
        } catch (\Throwable $e) {}

        // 2. Tambah username + photo di semua schema outlet (outlet_users)
        $schemas = DB::select("
            SELECT schema_name FROM information_schema.schemata
            WHERE schema_name NOT IN ('public','information_schema','pg_catalog','pg_toast')
            AND schema_name NOT LIKE 'pg_%'
        ");

        foreach ($schemas as $schema) {
            $s = $schema->schema_name;

            // Cek apakah tabel outlet_users ada
            $exists = DB::selectOne("
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = ? AND table_name = 'outlet_users'
            ", [$s]);
            if (!$exists) continue;

            $this->addColumnIfNotExists($s, 'outlet_users', 'username',
                "ALTER TABLE \"$s\".\"outlet_users\" ADD COLUMN IF NOT EXISTS username VARCHAR(64) NULL"
            );
            $this->addColumnIfNotExists($s, 'outlet_users', 'photo',
                "ALTER TABLE \"$s\".\"outlet_users\" ADD COLUMN IF NOT EXISTS photo TEXT NULL"
            );

            // Unique index username per schema
            try {
                DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS {$s}_outlet_users_username_unique ON \"$s\".\"outlet_users\" (username) WHERE username IS NOT NULL");
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE \"public\".\"users\" DROP COLUMN IF EXISTS username");

        $schemas = DB::select("
            SELECT schema_name FROM information_schema.schemata
            WHERE schema_name NOT IN ('public','information_schema','pg_catalog','pg_toast')
            AND schema_name NOT LIKE 'pg_%'
        ");
        foreach ($schemas as $schema) {
            $s = $schema->schema_name;
            DB::statement("ALTER TABLE \"$s\".\"outlet_users\" DROP COLUMN IF EXISTS username");
            DB::statement("ALTER TABLE \"$s\".\"outlet_users\" DROP COLUMN IF EXISTS photo");
        }
    }

    private function addColumnIfNotExists(string $schema, string $table, string $column, string $sql): void
    {
        $exists = DB::selectOne("
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = ? AND table_name = ? AND column_name = ?
        ", [$schema, $table, $column]);

        if (!$exists) {
            DB::statement($sql);
        }
    }
};
