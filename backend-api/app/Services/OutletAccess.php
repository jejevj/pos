<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Per-outlet RBAC / access service.
 *
 * Single source of truth for "can this authenticated global user touch
 * resources inside outlet X?". Three things matter:
 *
 *   1. Is the global user a platform superadmin?
 *   2. Is the global user the outlet's owner (outlets.user_id)?
 *   3. Is the global user mapped to an active outlet_user row inside the
 *      outlet's schema (matched by email)?
 *
 * Any one of the three grants base access. An optional permission name can
 * be enforced — for non-owner / non-superadmin staff, the resolved
 * outlet_user must have the permission via user_roles -> role_permissions.
 *
 * Side effect: this service leaves the connection's search_path on the
 * outlet schema when access is granted, so the calling controller can
 * continue running per-outlet queries without re-setting it. Callers are
 * still responsible for resetting search_path back to public at the end
 * of the request (existing controllers already do this).
 */
class OutletAccess
{
    public const CODE_NOT_OUTLET_EMPLOYEE = 'NOT_OUTLET_EMPLOYEE';
    public const CODE_MISSING_PERMISSION = 'MISSING_PERMISSION';
    public const CODE_OUTLET_NOT_FOUND = 'OUTLET_NOT_FOUND';
    public const CODE_UNAUTHENTICATED = 'UNAUTHENTICATED';

    /**
     * Authorize the currently authenticated user against an outlet.
     *
     * @param  int|string  $outletId
     * @param  array  $options  {
     *     @var string|null $permission         Required outlet permission (skipped for superadmin/owner unless $strict).
     *     @var bool        $allowSuperadmin    Default true. Set false for employee-self endpoints that even SA must not bypass.
     *     @var bool        $allowOwner         Default true. Set false to force every caller through outlet_users mapping.
     *     @var bool        $setSchema          Default true. Leave the search_path on the outlet schema for the caller.
     *     @var bool        $strictPermission   Default false. Force permission check even for owner/superadmin.
     * }
     *
     * @return array{outlet: Outlet, outlet_user: object|null, is_superadmin: bool, is_owner: bool}
     *
     * @throws HttpResponseException
     */
    public function authorize($outletId, array $options = []): array
    {
        $opts = array_merge([
            'permission' => null,
            'allowSuperadmin' => true,
            'allowOwner' => true,
            'setSchema' => true,
            'strictPermission' => false,
        ], $options);

        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            $this->deny(401, 'Unauthenticated', self::CODE_UNAUTHENTICATED);
        }

        $outlet = Outlet::find($outletId);
        if (!$outlet) {
            $this->deny(404, 'Outlet not found', self::CODE_OUTLET_NOT_FOUND);
        }
        if (!$outlet->is_active) {
            $this->deny(403, 'Outlet is inactive', 'OUTLET_INACTIVE');
        }

        $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : false;
        $isOwner = $outlet->user_id === $user->id;

        // Try to resolve outlet_user mapping (by email) even for owner/SA —
        // some employee-facing endpoints need the outlet_user.id even when
        // the caller is the owner.
        // Always set schema temporarily to query outlet_users — even when setSchema=false.
        // Without this, the query falls to the public schema where outlet_users does not exist.
        DB::statement("SET search_path TO {$outlet->schema_name}, public");

        $outletUser = null;
        try {
            $outletUser = DB::table('outlet_users')
                ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->first();
        } catch (\Throwable $e) {
            // Schema may not have outlet_users yet (very fresh outlet). Treat
            // as no mapping rather than 500ing the request.
            $outletUser = null;
        }

        // If caller does not want the schema to remain set, reset it now.
        if (!$opts['setSchema']) {
            DB::statement("SET search_path TO public");
        }

        $hasMapping = $outletUser !== null;

        $baseAccess =
            ($opts['allowSuperadmin'] && $isSuperAdmin)
            || ($opts['allowOwner'] && $isOwner)
            || $hasMapping;

        if (!$baseAccess) {
            if ($opts['setSchema']) {
                DB::statement("SET search_path TO public");
            }
            $this->deny(
                403,
                'Akun Anda tidak terdaftar sebagai pengguna outlet ini.',
                self::CODE_NOT_OUTLET_EMPLOYEE,
                ['outlet_id' => (int) $outlet->id]
            );
        }

        if ($opts['permission']) {
            $bypass = !$opts['strictPermission'] && (
                ($opts['allowSuperadmin'] && $isSuperAdmin)
                || ($opts['allowOwner'] && $isOwner)
            );
            if (!$bypass) {
                if (!$hasMapping || !$this->outletUserHasPermission($outletUser->id, $opts['permission'])) {
                    if ($opts['setSchema']) {
                        DB::statement("SET search_path TO public");
                    }
                    $this->deny(
                        403,
                        'Akun outlet Anda tidak memiliki izin: ' . $opts['permission'],
                        self::CODE_MISSING_PERMISSION,
                        ['required_permission' => $opts['permission']]
                    );
                }
            }
        }

        return [
            'outlet' => $outlet,
            'outlet_user' => $outletUser,
            'is_superadmin' => $isSuperAdmin,
            'is_owner' => $isOwner,
        ];
    }

    /**
     * Check if the resolved outlet_user has a permission, via user_roles
     * joined with role_permissions and permissions. Assumes search_path is
     * already on the outlet schema.
     */
    public function outletUserHasPermission(int $outletUserId, string $permission): bool
    {
        return DB::table('user_roles')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('user_roles.user_id', $outletUserId)
            ->where('permissions.name', $permission)
            ->exists();
    }

    /**
     * Whether the resolved outlet_user has any of the named roles.
     */
    public function outletUserHasRole(int $outletUserId, array $roleNames): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $outletUserId)
            ->whereIn('roles.name', $roleNames)
            ->exists();
    }

    private function deny(int $status, string $message, string $code, array $extra = []): void
    {
        $payload = array_merge(['message' => $message, 'code' => $code], $extra);
        throw new HttpResponseException(response()->json($payload, $status));
    }
}
