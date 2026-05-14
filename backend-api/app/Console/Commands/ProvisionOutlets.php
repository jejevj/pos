<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Models\User;
use App\Services\OutletProvisioner;
use Illuminate\Console\Command;

/**
 * Single entry point to provision the full per-outlet schema (schema +
 * outlet_users + RBAC seed + transaction/menu/bahan-baku/station/stock-opname
 * /promo/membership/HR/shift/kasbon/purchase-expense/employee-beverage
 * tables) for one or all outlets. Replaces the chain of individual
 * `outlets:create-*` commands for everyday use — those still work for
 * backward compatibility but new outlets no longer require them.
 *
 * Idempotent: every statement is CREATE TABLE IF NOT EXISTS / ADD COLUMN
 * IF NOT EXISTS, so re-running fills in whatever is missing without
 * dropping data.
 */
class ProvisionOutlets extends Command
{
    protected $signature = 'outlets:provision
        {--outlet-id= : Provision a single outlet by id (default: all outlets)}
        {--with-owner : Also map the outlet.user_id global user as an owner outlet_user}
        {--owner-user-id= : When --with-owner, use this users.id instead of outlet.user_id}';

    protected $description = 'Idempotently (re-)provision schema + tables + RBAC for outlets, and optionally map the owning global user as an outlet_user with the owner role.';

    public function handle(OutletProvisioner $provisioner): int
    {
        $outletId = $this->option('outlet-id');
        $outlets = $outletId ? Outlet::where('id', $outletId)->get() : Outlet::all();

        if ($outlets->isEmpty()) {
            $this->error($outletId ? "Outlet {$outletId} not found." : 'No outlets found.');
            return self::FAILURE;
        }

        $ownerOverride = $this->option('owner-user-id');
        $withOwner = $this->option('with-owner') || $ownerOverride !== null;
        $ok = 0;
        $failed = 0;

        foreach ($outlets as $outlet) {
            $this->line("→ Provisioning #{$outlet->id} {$outlet->name} ({$outlet->schema_name})");

            if (!$provisioner->provision($outlet)) {
                $this->error("  ✗ provision failed (check laravel.log)");
                $failed++;
                continue;
            }
            $this->info("  ✓ schema + tables OK");

            if ($withOwner) {
                $creatorId = $ownerOverride ?: $outlet->user_id;
                $creator = $creatorId ? User::find($creatorId) : null;
                if (!$creator) {
                    $this->warn("  - skip owner mapping: user id {$creatorId} not found");
                } else {
                    $provisioner->mapOwner($outlet, $creator);
                    $this->info("  ✓ mapped {$creator->email} as owner outlet_user");
                }
            }
            $ok++;
        }

        $this->newLine();
        $this->info("Done. Provisioned: {$ok}, failed: {$failed}");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
