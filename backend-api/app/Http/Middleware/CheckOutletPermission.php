<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckOutletPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Super admin from public schema has full access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Get outlet from route parameter
        $outletId = $request->route('outletId');
        
        if (!$outletId) {
            return response()->json(['message' => 'Outlet not found'], 404);
        }

        // Get outlet
        $outlet = \App\Models\Outlet::find($outletId);
        
        if (!$outlet) {
            return response()->json(['message' => 'Outlet not found'], 404);
        }

        // Check if user is outlet owner
        if ($outlet->user_id === $user->id) {
            return $next($request);
        }

        // Check if user is outlet staff with permission
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            // Get outlet user
            $outletUser = DB::table('outlet_users')
                ->where('email', $user->email)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->first();
            
            if (!$outletUser) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Unauthorized - Not an outlet staff'], 403);
            }

            // Check if user has permission through role
            $hasPermission = DB::table('user_roles')
                ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
                ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->where('user_roles.user_id', $outletUser->id)
                ->where('permissions.name', $permission)
                ->exists();
            
            DB::statement("SET search_path TO public");
            
            if (!$hasPermission) {
                return response()->json([
                    'message' => 'Unauthorized - Insufficient permissions',
                    'required_permission' => $permission
                ], 403);
            }

            // Store outlet user in request for later use
            $request->attributes->set('outlet_user', $outletUser);
            
            return $next($request);
            
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Error checking permissions: ' . $e->getMessage()], 500);
        }
    }
}
