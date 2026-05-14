<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Services\OutletProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent full-schema migration for outlet schemas.
 *
 * Re-runs the {@see OutletProvisioner} (which uses CREATE TABLE IF NOT
 * EXISTS / ADD COLUMN IF NOT EXISTS throughout) against one or every
 * outlet, then applies a safety pass for late-added columns and the
 * production role/permissions seed so older outlets catch up to the
 * current schema without needing a fresh provision.
 *
 * Safe to run repeatedly — never drops, truncates, or rewrites data.
 */
class OutletMigrate extends Command
{
    protected $signature = 'outlet:migrate
                            {--outlet= : Specific outlet ID to migrate (default: all outlets)}';

    protected $description = 'Idempotently migrate all outlet schemas to the current table layout (CREATE/ALTER IF NOT EXISTS) and seed production role/permissions.';

    public function handle(OutletProvisioner $provisioner): int
    {
        $outletId = $this->option('outlet');

        $outlets = $outletId
            ? Outlet::where('id', $outletId)->get()
            : Outlet::all();

        if ($outlets->isEmpty()) {
            $this->error($outletId
                ? "Outlet dengan ID {$outletId} tidak ditemukan."
                : 'Tidak ada outlet di database.'
            );
            return self::FAILURE;
        }

        $success = 0;
        $failed  = 0;
        $rows    = [];

        foreach ($outlets as $outlet) {
            $this->line('');
            $this->line('─────────────────────────────────────────────────');
            $this->info("Outlet [{$outlet->id}]: {$outlet->name}");
            $this->line("Schema: {$outlet->schema_name}");

            try {
                if (!$provisioner->provision($outlet)) {
                    throw new \RuntimeException('Provisioner returned false — check laravel.log');
                }
                $this->line('  ✓ Tables provisioned (CREATE TABLE IF NOT EXISTS)');

                $this->applyLateColumns($outlet->schema_name);
                $this->line('  ✓ Late columns ensured (kategori_menu.station_id, order_items timing)');

                $seed = $this->seedProductionRbac($outlet->schema_name);
                $this->line(sprintf(
                    '  ✓ Production RBAC — permissions: %d new / %d existing, role: %s, assignments: %d new',
                    $seed['perms_inserted'],
                    $seed['perms_existing'],
                    $seed['role_status'],
                    $seed['assignments_inserted']
                ));

                $rows[] = [
                    $outlet->id,
                    $outlet->name,
                    $outlet->schema_name,
                    'OK',
                    $seed['perms_inserted'],
                    $seed['assignments_inserted'],
                ];
                $success++;
            } catch (\Throwable $e) {
                $this->error('  ✗ Gagal: ' . $e->getMessage());
                $rows[] = [
                    $outlet->id,
                    $outlet->name,
                    $outlet->schema_name,
                    'FAILED',
                    '-',
                    '-',
                ];
                $failed++;
            }
        }

        $this->line('');
        $this->line('─────────────────────────────────────────────────');
        $this->table(
            ['ID', 'Outlet', 'Schema', 'Status', 'New perms', 'New role-perm links'],
            $rows
        );
        $this->info("Selesai. Outlet berhasil: {$success} | Gagal: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * ALTER TABLE ... ADD COLUMN IF NOT EXISTS for columns introduced
     * after the original provisioner shipped. Kept here (in addition to
     * the provisioner) so this command remains a single, audit-friendly
     * statement of the post-migration column set.
     */
    private function applyLateColumns(string $schema): void
    {
        DB::statement("ALTER TABLE IF EXISTS {$schema}.kategori_menu ADD COLUMN IF NOT EXISTS station_id INTEGER");
        DB::statement("ALTER TABLE IF EXISTS {$schema}.order_items  ADD COLUMN IF NOT EXISTS preparing_at TIMESTAMP NULL");
        DB::statement("ALTER TABLE IF EXISTS {$schema}.order_items  ADD COLUMN IF NOT EXISTS ready_at     TIMESTAMP NULL");
        DB::statement("ALTER TABLE IF EXISTS {$schema}.order_items  ADD COLUMN IF NOT EXISTS served_at    TIMESTAMP NULL");
    }

    /**
     * Skip-if-exists seed for the production role + view/manage permissions,
     * mirroring {@see OutletProvisioner::seedRbac()}'s pattern. Also grants
     * the two permissions to owner & manager roles when those rows exist.
     *
     * role_permissions has no updated_at column — only insert created_at.
     */
    private function seedProductionRbac(string $schema): array
    {
        $result = [
            'perms_inserted'       => 0,
            'perms_existing'       => 0,
            'role_status'          => 'unchanged',
            'assignments_inserted' => 0,
        ];

        try {
            DB::statement("SET search_path TO {$schema}, public");

            $permDefs = [
                ['name' => 'view_production',   'display_name' => 'Lihat Unit Produksi', 'group_name' => 'production'],
                ['name' => 'manage_production', 'display_name' => 'Kelola Unit Produksi', 'group_name' => 'production'],
            ];

            $permIds = [];
            $now = now();
            foreach ($permDefs as $p) {
                $existing = DB::table('permissions')->where('name', $p['name'])->first();
                if ($existing) {
                    $permIds[$p['name']] = $existing->id;
                    $result['perms_existing']++;
                } else {
                    $permIds[$p['name']] = DB::table('permissions')->insertGetId([
                        'name'         => $p['name'],
                        'display_name' => $p['display_name'],
                        'group_name'   => $p['group_name'],
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                    $result['perms_inserted']++;
                }
            }

            $productionRole = DB::table('roles')->where('name', 'production')->first();
            if ($productionRole) {
                $productionRoleId = $productionRole->id;
            } else {
                $productionRoleId = DB::table('roles')->insertGetId([
                    'name'         => 'production',
                    'display_name' => 'Unit Produksi',
                    'description'  => 'Operator unit produksi',
                    'level'        => 45,
                    'is_active'    => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
                $result['role_status'] = 'created';
            }

            $rolesToGrant = ['production' => $productionRoleId];
            foreach (['owner', 'manager'] as $roleName) {
                $row = DB::table('roles')->where('name', $roleName)->first();
                if ($row) {
                    $rolesToGrant[$roleName] = $row->id;
                }
            }

            foreach ($rolesToGrant as $roleId) {
                foreach ($permIds as $permId) {
                    $exists = DB::table('role_permissions')
                        ->where('role_id', $roleId)
                        ->where('permission_id', $permId)
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    DB::table('role_permissions')->insert([
                        'role_id'       => $roleId,
                        'permission_id' => $permId,
                        'created_at'    => $now,
                    ]);
                    $result['assignments_inserted']++;
                }
            }
        } finally {
            DB::statement('SET search_path TO public');
        }

        return $result;
    }
}
