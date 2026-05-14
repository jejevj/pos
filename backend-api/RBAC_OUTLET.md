# Per-Outlet RBAC / Access Isolation

This document explains how authentication and authorization map to outlets,
and how to enforce per-outlet isolation in new endpoints.

## Mental model

There are TWO user tables:

1. **`public.users`** — the global authenticated user (Sanctum auth). The
   `user_id` in `outlets` table points here. Roles like `superadmin` /
   `admin` live in `public.roles` and are general platform roles.

2. **`<outlet_schema>.outlet_users`** — per-outlet employees with their own
   role/permission tables (`roles`, `permissions`, `user_roles`,
   `role_permissions`) inside the outlet's PostgreSQL schema.

A single global `users.email` may be mapped into many outlets — once per
outlet, by creating a matching `outlet_users` row in that outlet's schema.
The mapping key is **email**: when the global user hits
`/api/outlets/{id}/...`, we resolve them to an `outlet_users` row where
`email = users.email AND is_active = true AND deleted_at IS NULL`.

## The access rule

For any endpoint scoped to an outlet (`/api/outlets/{outletId}/...`), a
caller is allowed in if **at least one** of the following is true:

1. The global user has the `superadmin` role (platform admin).
2. The global user owns the outlet (`outlets.user_id = users.id`).
3. The global user is mapped to an active `outlet_users` row in the
   outlet's schema (matched by email).

If the endpoint requires an outlet permission, the resolved `outlet_user`
must additionally have that permission via `user_roles` →
`role_permissions` → `permissions.name`. By default, superadmin and the
outlet owner bypass the per-permission check (set `strictPermission` to
require it for them too).

### Employee-only endpoints

Some endpoints belong to the employee themselves (clock in/out, claim
beverage, view own quota). Even a superadmin must NOT bypass — you cannot
clock in for an outlet you don't actually work at. These endpoints call
`authorizeOutletAsEmployee()` which forces `allowSuperadmin = false`
and `allowOwner = false`. The owner only passes if they ALSO have an
`outlet_users` row (which the outlet provisioner now creates automatically
on outlet creation).

## How to use it in a new controller

```php
use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;

class FooController extends Controller
{
    use AuthorizesOutletAccess;

    public function index(Request $r, $outletId)
    {
        // Authorize + set search_path to {schema},public
        $outlet = $this->authorizeOutlet($outletId);
        // ...queries against tables inside the outlet schema...
        DB::statement("SET search_path TO public"); // reset on the way out
    }

    public function importantWrite(Request $r, $outletId)
    {
        // Require a specific outlet permission. SA/owner still pass unless
        // strictPermission is set.
        $outlet = $this->authorizeOutlet($outletId, [
            'permission' => 'orders.refund',
        ]);
    }

    public function selfOnly(Request $r, $outletId)
    {
        // Employee-only — superadmin / owner cannot bypass.
        $outlet = $this->authorizeOutletAsEmployee($outletId);
        $me = $this->currentOutletUser(); // never null here
    }
}
```

## Error shapes

All denials return JSON with a stable `code`:

| HTTP | Code                    | When                                                   |
|------|-------------------------|--------------------------------------------------------|
| 401  | `UNAUTHENTICATED`       | No Sanctum token / expired token.                      |
| 404  | `OUTLET_NOT_FOUND`      | Outlet id does not exist.                              |
| 403  | `OUTLET_INACTIVE`       | Outlet exists but `is_active = false`.                 |
| 403  | `NOT_OUTLET_EMPLOYEE`   | User is neither SA / owner nor mapped via outlet_users |
| 403  | `MISSING_PERMISSION`    | User is mapped but lacks the per-outlet permission     |
| 403  | `OUTLET_ADMIN_REQUIRED` | Mutation that requires owner / superadmin only         |
| 403  | `SUPERADMIN_REQUIRED`   | Platform-admin-only action (WAHA session control)      |

## Why an authenticated user from outlet A cannot read outlet B

When a user from outlet A authenticates and hits
`/api/outlets/{B_id}/...`, `authorizeOutlet()` runs against outlet B's
schema:

- They are not the platform superadmin (no `superadmin` role on
  `public.users`).
- They do not own outlet B (`outlets.user_id != users.id`).
- They have **no** active `outlet_users` row in outlet B's schema
  (different schema, different table, no mapping).

So the guard returns `403 NOT_OUTLET_EMPLOYEE` and the controller body
never runs. Schema isolation is structurally enforced — every
outlet-scoped query runs inside `SET search_path TO <outlet_schema>`,
which the guard sets only after access is granted, so even if a query
omitted an `outlet_id` filter, it would still target B's schema only if
the caller is allowed in B.

## Manual verification

Set up two outlets with two distinct mapped staff users, then run:

```bash
BASE=http://localhost:8000/api
TOK_STAFF_A=...   # staff mapped to outlet A only
TOK_STAFF_B=...   # staff mapped to outlet B only
TOK_SA=...        # platform superadmin

# 1. Staff A reads outlet A — 200
curl -s -H "Authorization: Bearer $TOK_STAFF_A" "$BASE/outlets/A_ID/menu" | jq .

# 2. Staff A tries outlet B — 403 NOT_OUTLET_EMPLOYEE
curl -s -H "Authorization: Bearer $TOK_STAFF_A" "$BASE/outlets/B_ID/menu" | jq .

# 3. Superadmin reads anything — 200
curl -s -H "Authorization: Bearer $TOK_SA" "$BASE/outlets/B_ID/menu" | jq .

# 4. Previously open endpoints — now protected
curl -s -H "Authorization: Bearer $TOK_STAFF_A" "$BASE/outlets/B_ID/weather/latest" | jq .
curl -s -H "Authorization: Bearer $TOK_STAFF_A" "$BASE/outlets/B_ID/employee-beverages/settings" | jq .
curl -s -H "Authorization: Bearer $TOK_STAFF_A" "$BASE/outlets/B_ID/roles" | jq .
curl -s -H "Authorization: Bearer $TOK_STAFF_A" "$BASE/outlets/B_ID/whatsapp/status" | jq .
# All three should return 403 with code NOT_OUTLET_EMPLOYEE.

# 5. Employee-only — superadmin cannot bypass without an outlet_users row
#    (assumes SA has no outlet_user mapping in outlet B)
curl -s -X POST -H "Authorization: Bearer $TOK_SA" \
     -H 'Content-Type: application/json' \
     -d '{"menu_id":1}' \
     "$BASE/outlets/B_ID/employee-beverages/claim" | jq .
# -> 403 NOT_OUTLET_EMPLOYEE

# 6. Outlet-admin mutation — only owner / SA
curl -s -X POST -H "Authorization: Bearer $TOK_STAFF_A" \
     -H 'Content-Type: application/json' \
     -d '{"name":"x","display_name":"X","level":10}' \
     "$BASE/outlets/A_ID/roles" | jq .
# -> 403 OUTLET_ADMIN_REQUIRED (Staff A is mapped but is not owner/SA)
```

## Migration notes

The legacy per-controller `private function authorizeOutlet($outletId)`
helpers came in three flavours, all of them wrong:

1. Owner-only: `outlets.user_id !== users.id → 403`. Locked out every
   non-owner staff user, even those mapped via outlet_users.
2. Existence-only: returned the outlet as long as it existed; no access
   check whatsoever.
3. Missing: any authenticated user could read/write the outlet schema by
   path (Weather, EmployeeBeverage, RolePermission, WhatsApp).

These have all been replaced by `AuthorizesOutletAccess::authorizeOutlet()`
which implements the rule above and sets the schema search_path in one
call.
