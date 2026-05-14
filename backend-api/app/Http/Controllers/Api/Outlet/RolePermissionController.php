<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RolePermissionController extends Controller
{
    use AuthorizesOutletAccess;

    /**
     * Resolve the outlet, authorize the caller, set search_path, and return
     * the schema name. Reading roles/permissions and listing user
     * permissions is allowed for any mapped outlet_user; mutating roles
     * (create/update/delete) and assigning roles to other users requires
     * outlet admin (owner / superadmin).
     */
    private function authorizeAndUseSchema($outletId, bool $requireAdmin = false): string
    {
        $opts = ['setSchema' => true];
        if ($requireAdmin) {
            // Outlet-admin-only mutation. Don't strictPermission since owner
            // implicitly has full rights; just gate by isOutletAdmin().
        }
        $outlet = $this->authorizeOutlet($outletId, $opts);
        if ($requireAdmin && !$this->isOutletAdmin()) {
            DB::statement("SET search_path TO public");
            abort(response()->json([
                'message' => 'Hanya admin/owner outlet yang boleh mengubah peran & izin.',
                'code' => 'OUTLET_ADMIN_REQUIRED',
            ], 403));
        }
        return $outlet->schema_name;
    }
    /**
     * Get all roles with their permissions
     */
    public function getRoles(Request $request, $outletId)
    {
        try {
            $schemaName = $this->authorizeAndUseSchema($outletId);

            $roles = DB::table('roles')
                ->orderBy('level', 'desc')
                ->orderBy('name')
                ->get();

            foreach ($roles as $role) {
                $permissions = DB::table('role_permissions')
                    ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                    ->where('role_permissions.role_id', $role->id)
                    ->select('permissions.*')
                    ->get();
                
                $role->permissions = $permissions;
                
                // Count users with this role
                $role->users_count = DB::table('user_roles')
                    ->where('role_id', $role->id)
                    ->count();
            }

            DB::statement("SET search_path TO public");
            return response()->json($roles);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to fetch roles', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all permissions grouped by group_name
     */
    public function getPermissions(Request $request, $outletId)
    {
        try {
            $schemaName = $this->authorizeAndUseSchema($outletId);

            $permissions = DB::table('permissions')
                ->orderBy('group_name')
                ->orderBy('name')
                ->get();

            // Group by group_name
            $grouped = $permissions->groupBy('group_name');

            DB::statement("SET search_path TO public");
            return response()->json($grouped);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to fetch permissions', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new role
     */
    public function createRole(Request $request, $outletId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'level' => 'required|integer|min:0|max:100',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $schemaName = $this->authorizeAndUseSchema($outletId, true);

            // Check if role name already exists
            $exists = DB::table('roles')->where('name', $request->name)->exists();
            if ($exists) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Role name already exists'], 422);
            }

            // Create role
            $roleId = DB::table('roles')->insertGetId([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'level' => $request->level,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Assign permissions
            if ($request->has('permissions') && is_array($request->permissions)) {
                foreach ($request->permissions as $permissionId) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => now()
                    ]);
                }
            }

            $role = DB::table('roles')->where('id', $roleId)->first();

            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Role created successfully', 'role' => $role], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to create role', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a role
     */
    public function updateRole(Request $request, $outletId, $roleId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:50',
            'display_name' => 'string|max:100',
            'description' => 'nullable|string',
            'level' => 'integer|min:0|max:100',
            'is_active' => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $schemaName = $this->authorizeAndUseSchema($outletId, true);

            $role = DB::table('roles')->where('id', $roleId)->first();
            if (!$role) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Role not found'], 404);
            }

            // Prevent editing system roles (owner, admin)
            if (in_array($role->name, ['owner', 'admin'])) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Cannot edit system roles'], 403);
            }

            // Update role
            $updateData = array_filter([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'level' => $request->level,
                'is_active' => $request->is_active,
                'updated_at' => now()
            ], function($value) {
                return $value !== null;
            });

            DB::table('roles')->where('id', $roleId)->update($updateData);

            // Update permissions if provided
            if ($request->has('permissions')) {
                // Delete existing permissions
                DB::table('role_permissions')->where('role_id', $roleId)->delete();
                
                // Insert new permissions
                if (is_array($request->permissions)) {
                    foreach ($request->permissions as $permissionId) {
                        DB::table('role_permissions')->insert([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                            'created_at' => now()
                        ]);
                    }
                }
            }

            $updatedRole = DB::table('roles')->where('id', $roleId)->first();

            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Role updated successfully', 'role' => $updatedRole]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to update role', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a role
     */
    public function deleteRole(Request $request, $outletId, $roleId)
    {
        try {
            $schemaName = $this->authorizeAndUseSchema($outletId, true);

            $role = DB::table('roles')->where('id', $roleId)->first();
            if (!$role) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Role not found'], 404);
            }

            // Prevent deleting system roles
            if (in_array($role->name, ['owner', 'admin', 'staff'])) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Cannot delete system roles'], 403);
            }

            // Check if role has users
            $usersCount = DB::table('user_roles')->where('role_id', $roleId)->count();
            if ($usersCount > 0) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => "Cannot delete role with {$usersCount} assigned users"], 422);
            }

            DB::table('roles')->where('id', $roleId)->delete();

            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Role deleted successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to delete role', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Assign role to user
     */
    public function assignRole(Request $request, $outletId)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'role_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $schemaName = $this->authorizeAndUseSchema($outletId, true);

            // Check if user exists
            $user = DB::table('outlet_users')->where('id', $request->user_id)->first();
            if (!$user) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'User not found'], 404);
            }

            // Check if role exists
            $role = DB::table('roles')->where('id', $request->role_id)->first();
            if (!$role) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Role not found'], 404);
            }

            // Check if already assigned
            $exists = DB::table('user_roles')
                ->where('user_id', $request->user_id)
                ->where('role_id', $request->role_id)
                ->exists();

            if ($exists) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Role already assigned to user'], 422);
            }

            // Assign role
            DB::table('user_roles')->insert([
                'user_id' => $request->user_id,
                'role_id' => $request->role_id,
                'created_at' => now()
            ]);

            // Update primary role_id in outlet_users
            DB::table('outlet_users')
                ->where('id', $request->user_id)
                ->update(['role_id' => $request->role_id]);

            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Role assigned successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to assign role', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(Request $request, $outletId, $userId)
    {
        try {
            $schemaName = $this->authorizeAndUseSchema($outletId);
            // Non-admin staff may only read their own permissions.
            if (!$this->isOutletAdmin()) {
                $current = $this->currentOutletUser();
                if (!$current || (int) $current->id !== (int) $userId) {
                    DB::statement("SET search_path TO public");
                    return response()->json([
                        'message' => 'Anda hanya bisa melihat izin akun outlet Anda sendiri.',
                        'code' => 'OUTLET_ADMIN_REQUIRED',
                    ], 403);
                }
            }

            $permissions = DB::table('user_roles')
                ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
                ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->where('user_roles.user_id', $userId)
                ->select('permissions.*')
                ->distinct()
                ->get();

            DB::statement("SET search_path TO public");
            return response()->json($permissions);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to fetch user permissions', 'error' => $e->getMessage()], 500);
        }
    }

}
