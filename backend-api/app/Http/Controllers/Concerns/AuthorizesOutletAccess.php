<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Outlet;
use App\Services\OutletAccess;
use Illuminate\Support\Facades\DB;

/**
 * Drop-in helper for outlet-scoped controllers.
 *
 * Replaces the historical per-controller authorizeOutlet() helpers, which
 * came in three flavors:
 *
 *   - owner-only (rejected non-owner staff who are mapped in outlet_users)
 *   - existence-only (no access check at all — clear bug)
 *   - missing entirely (every authenticated user could read/write any outlet)
 *
 * The unified rule is:
 *
 *   superadmin OR outlets.user_id == user.id OR active outlet_users row
 *   (matched by email) in the outlet's schema.
 *
 * Optionally, callers can require a per-outlet permission name.
 *
 * Example:
 *
 *   $outlet = $this->authorizeOutlet($outletId, ['permission' => 'orders.view']);
 *   // search_path is now set to {$outlet->schema_name},public
 *   // $this->currentOutletUser() returns the resolved outlet_user (or null
 *   // for superadmin/owner with no outlet_users row).
 */
trait AuthorizesOutletAccess
{
    protected ?object $resolvedOutletUser = null;
    protected bool $resolvedIsSuperAdmin = false;
    protected bool $resolvedIsOwner = false;

    /**
     * Authorize the current request against the given outlet and return the
     * Outlet model. Sets the connection search_path to the outlet schema.
     *
     * @param  int|string  $outletId
     * @param  array  $options  See OutletAccess::authorize()
     */
    protected function authorizeOutlet($outletId, array $options = []): Outlet
    {
        /** @var OutletAccess $access */
        $access = app(OutletAccess::class);
        $result = $access->authorize($outletId, $options);

        $this->resolvedOutletUser = $result['outlet_user'];
        $this->resolvedIsSuperAdmin = $result['is_superadmin'];
        $this->resolvedIsOwner = $result['is_owner'];

        return $result['outlet'];
    }

    /**
     * Authorize against an outlet and additionally require an outlet_user
     * mapping. Use this for employee-facing endpoints (attendance,
     * employee-beverage claims) where superadmin/owner must NOT bypass.
     */
    protected function authorizeOutletAsEmployee($outletId, array $options = []): Outlet
    {
        $options['allowSuperadmin'] = false;
        $options['allowOwner'] = false;

        $outlet = $this->authorizeOutlet($outletId, $options);

        if (!$this->resolvedOutletUser) {
            DB::statement("SET search_path TO public");
            abort(response()->json([
                'message' => 'Akun Anda tidak terdaftar sebagai karyawan di outlet ini.',
                'code' => 'NOT_OUTLET_EMPLOYEE',
            ], 403));
        }

        return $outlet;
    }

    protected function currentOutletUser(): ?object
    {
        return $this->resolvedOutletUser;
    }

    protected function isResolvedSuperAdmin(): bool
    {
        return $this->resolvedIsSuperAdmin;
    }

    protected function isResolvedOwner(): bool
    {
        return $this->resolvedIsOwner;
    }

    /**
     * True if the caller is a superadmin or outlet owner — i.e. an admin-
     * style caller for this outlet. Useful for "only managers can see
     * everyone else's records" branches.
     */
    protected function isOutletAdmin(): bool
    {
        return $this->resolvedIsSuperAdmin || $this->resolvedIsOwner;
    }
}
