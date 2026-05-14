<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OutletUser extends Model
{
    use HasFactory;

    // This model doesn't use Eloquent's default table
    // It will work with dynamic schema tables
    
    protected $fillable = [
        'outlet_id',
        'name',
        'email',
        'password',
        'phone',
        'role',
        'is_active',
        'settings',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Create a new outlet user in the specified schema
     */
    public static function createInSchema($schemaName, array $data)
    {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Set schema
        DB::statement("SET search_path TO {$schemaName}, public");

        // Insert user
        $userId = DB::table('outlet_users')->insertGetId($data);

        // Reset schema
        DB::statement("SET search_path TO public");

        return $userId;
    }

    /**
     * Update outlet user in the specified schema
     */
    public static function updateInSchema($schemaName, $id, array $data)
    {
        // Hash password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['updated_at'] = now();

        // Set schema
        DB::statement("SET search_path TO {$schemaName}, public");

        // Update user
        $result = DB::table('outlet_users')
            ->where('id', $id)
            ->update($data);

        // Reset schema
        DB::statement("SET search_path TO public");

        return $result;
    }

    /**
     * Delete outlet user in the specified schema (soft delete)
     */
    public static function deleteInSchema($schemaName, $id)
    {
        // Set schema
        DB::statement("SET search_path TO {$schemaName}, public");

        // Soft delete
        $result = DB::table('outlet_users')
            ->where('id', $id)
            ->update(['deleted_at' => now()]);

        // Reset schema
        DB::statement("SET search_path TO public");

        return $result;
    }

    /**
     * Get all outlet users from the specified schema
     */
    public static function getAllFromSchema($schemaName, $outletId)
    {
        // Set schema
        DB::statement("SET search_path TO {$schemaName}, public");

        // Get users
        $users = DB::table('outlet_users')
            ->where('outlet_id', $outletId)
            ->whereNull('deleted_at')
            ->get();

        // Reset schema
        DB::statement("SET search_path TO public");

        return $users;
    }

    /**
     * Get user's roles in the specified schema
     */
    public static function getUserRoles($schemaName, $userId)
    {
        DB::statement("SET search_path TO {$schemaName}, public");
        
        $roles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $userId)
            ->select('roles.*')
            ->get();
        
        DB::statement("SET search_path TO public");
        
        return $roles;
    }

    /**
     * Get user's permissions in the specified schema
     */
    public static function getUserPermissions($schemaName, $userId)
    {
        DB::statement("SET search_path TO {$schemaName}, public");
        
        $permissions = DB::table('user_roles')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('user_roles.user_id', $userId)
            ->select('permissions.*')
            ->distinct()
            ->get();
        
        DB::statement("SET search_path TO public");
        
        return $permissions;
    }

    /**
     * Check if user has permission in the specified schema
     */
    public static function hasPermission($schemaName, $userId, $permissionName)
    {
        DB::statement("SET search_path TO {$schemaName}, public");
        
        $hasPermission = DB::table('user_roles')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('user_roles.user_id', $userId)
            ->where('permissions.name', $permissionName)
            ->exists();
        
        DB::statement("SET search_path TO public");
        
        return $hasPermission;
    }

    /**
     * Check if user has role in the specified schema
     */
    public static function hasRole($schemaName, $userId, $roleName)
    {
        DB::statement("SET search_path TO {$schemaName}, public");
        
        $hasRole = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $userId)
            ->where('roles.name', $roleName)
            ->exists();
        
        DB::statement("SET search_path TO public");
        
        return $hasRole;
    }

    /**
     * Assign role to user in the specified schema
     */
    public static function assignRole($schemaName, $userId, $roleId)
    {
        DB::statement("SET search_path TO {$schemaName}, public");
        
        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $userId, 'role_id' => $roleId],
            ['created_at' => now()]
        );
        
        // Also update role_id in outlet_users for quick reference
        DB::table('outlet_users')
            ->where('id', $userId)
            ->update(['role_id' => $roleId, 'updated_at' => now()]);
        
        DB::statement("SET search_path TO public");
    }

    /**
     * Remove role from user in the specified schema
     */
    public static function removeRole($schemaName, $userId, $roleId)
    {
        DB::statement("SET search_path TO {$schemaName}, public");
        
        DB::table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->delete();
        
        DB::statement("SET search_path TO public");
    }

    /**
     * Get single outlet user from the specified schema
     */
    public static function getFromSchema($schemaName, $id)
    {
        // Set schema
        DB::statement("SET search_path TO {$schemaName}, public");

        // Get user
        $user = DB::table('outlet_users')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        // Reset schema
        DB::statement("SET search_path TO public");

        return $user;
    }
}
