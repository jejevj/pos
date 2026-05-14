<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\OutletUser;
use App\Services\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Outlet Users",
 *     description="Outlet user management endpoints"
 * )
 */
class OutletUserController extends Controller
{
    private OutletAccess $access;

    public function __construct(OutletAccess $access)
    {
        $this->access = $access;
    }

    /**
     * @OA\Get(
     *     path="/api/outlets/{outlet}/users",
     *     summary="Get all users for an outlet",
     *     tags={"Outlet Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function index($outletId)
    {
        ['outlet' => $outlet] = $this->access->authorize($outletId, [
            'permission' => 'manage_users',
            'setSchema'  => false,
        ]);

        try {
            $users = OutletUser::getAllFromSchema($outlet->schema_name, $outlet->id);

            return response()->json([
                'users'  => $users,
                'outlet' => $outlet,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch users',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/outlets/{outlet}/users",
     *     summary="Create new outlet user",
     *     tags={"Outlet Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request, $outletId)
    {
        ['outlet' => $outlet] = $this->access->authorize($outletId, [
            'permission' => 'manage_users',
            'setSchema'  => false,
        ]);

        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'password'  => 'required|string|min:6',
            'phone'     => 'nullable|string|max:50',
            'role_id'   => 'required|integer',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['outlet_id']  = $outlet->id;
            $data['created_at'] = now();
            $data['updated_at'] = now();

            $userId = OutletUser::createInSchema($outlet->schema_name, $data);

            if (isset($data['role_id'])) {
                OutletUser::assignRole($outlet->schema_name, $userId, $data['role_id']);
            }

            $newUser              = OutletUser::getFromSchema($outlet->schema_name, $userId);
            $newUser->roles       = OutletUser::getUserRoles($outlet->schema_name, $userId);
            $newUser->permissions = OutletUser::getUserPermissions($outlet->schema_name, $userId);

            return response()->json([
                'message' => 'User created successfully',
                'user'    => $newUser,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/outlets/{outlet}/users/{id}",
     *     summary="Get outlet user by ID",
     *     tags={"Outlet Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function show($outletId, $id)
    {
        ['outlet' => $outlet] = $this->access->authorize($outletId, [
            'permission' => 'manage_users',
            'setSchema'  => false,
        ]);

        try {
            $outletUser = OutletUser::getFromSchema($outlet->schema_name, $id);

            if (!$outletUser) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $outletUser->roles       = OutletUser::getUserRoles($outlet->schema_name, $id);
            $outletUser->permissions = OutletUser::getUserPermissions($outlet->schema_name, $id);

            return response()->json($outletUser);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch user',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/outlets/{outlet}/users/{id}",
     *     summary="Update outlet user",
     *     tags={"Outlet Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function update(Request $request, $outletId, $id)
    {
        ['outlet' => $outlet] = $this->access->authorize($outletId, [
            'permission' => 'manage_users',
            'setSchema'  => false,
        ]);

        $validator = Validator::make($request->all(), [
            'name'      => 'string|max:255',
            'email'     => 'email|max:255',
            'password'  => 'nullable|string|min:6',
            'phone'     => 'nullable|string|max:50',
            'role_id'   => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            OutletUser::updateInSchema($outlet->schema_name, $id, $data);

            if (isset($data['role_id'])) {
                \Illuminate\Support\Facades\DB::statement("SET search_path TO {$outlet->schema_name}, public");
                \Illuminate\Support\Facades\DB::table('user_roles')->where('user_id', $id)->delete();
                \Illuminate\Support\Facades\DB::statement('SET search_path TO public');

                OutletUser::assignRole($outlet->schema_name, $id, $data['role_id']);
            }

            $updatedUser              = OutletUser::getFromSchema($outlet->schema_name, $id);
            $updatedUser->roles       = OutletUser::getUserRoles($outlet->schema_name, $id);
            $updatedUser->permissions = OutletUser::getUserPermissions($outlet->schema_name, $id);

            return response()->json([
                'message' => 'User updated successfully',
                'user'    => $updatedUser,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/outlets/{outlet}/users/{id}",
     *     summary="Delete outlet user",
     *     tags={"Outlet Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function destroy($outletId, $id)
    {
        ['outlet' => $outlet] = $this->access->authorize($outletId, [
            'permission' => 'manage_users',
            'setSchema'  => false,
        ]);

        try {
            OutletUser::deleteInSchema($outlet->schema_name, $id);

            return response()->json(['message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete user',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
