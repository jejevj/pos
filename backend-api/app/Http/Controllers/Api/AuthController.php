<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/api/auth/register",
        tags: ["Authentication"],
        summary: "Register user baru",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "password123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "User berhasil didaftarkan",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", type: "object"),
                        new OA\Property(property: "token", type: "string")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->load(['roles.permissions']);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    #[OA\Post(
        path: "/api/auth/login",
        tags: ["Authentication"],
        summary: "Login user",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login berhasil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", type: "object"),
                        new OA\Property(property: "token", type: "string")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Invalid credentials")
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::with(['roles.permissions'])->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'outlet_memberships' => $this->resolveOutletMemberships($user),
        ]);
    }

    #[OA\Post(
        path: "/api/auth/logout",
        tags: ["Authentication"],
        summary: "Logout user",
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Logout berhasil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Logged out successfully")
                    ]
                )
            )
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    #[OA\Get(
        path: "/api/auth/user",
        tags: ["Authentication"],
        summary: "Get authenticated user",
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "User data",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", type: "object")
                    ]
                )
            )
        ]
    )]
    public function user(Request $request)
    {
        $user = $request->user()->load(['roles.permissions']);
        
        return response()->json([
            'user' => $user,
            'outlet_memberships' => $this->resolveOutletMemberships($user),
        ]);
    }

    /**
     * Resolve which outlets this user is a member of (via outlet_users table),
     * along with their role name and permissions in each outlet.
     *
     * Returns an array keyed by outlet_id:
     * [
     *   ['outlet_id' => 1, 'outlet_name' => 'Outlet A', 'schema' => 'outlet_1',
     *    'role' => 'kasir', 'permissions' => ['view_pos', 'create_order', ...]]
     * ]
     */
    private function resolveOutletMemberships(User $user): array
    {
        $memberships = [];

        try {
            $outlets = Outlet::where('is_active', true)->get();

            foreach ($outlets as $outlet) {
                try {
                    DB::statement("SET search_path TO {$outlet->schema_name}, public");

                    $outletUser = DB::table('outlet_users')
                        ->where('email', strtolower($user->email))
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$outletUser) continue;

                    // Get role(s) for this outlet_user
                    $roles = DB::table('user_roles')
                        ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                        ->where('user_roles.outlet_user_id', $outletUser->id)
                        ->select('roles.id as role_id', 'roles.name', 'roles.display_name')
                        ->get();

                    $roleIds = $roles->pluck('role_id');

                    // Get permissions for those roles
                    $permissions = DB::table('role_permissions')
                        ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                        ->whereIn('role_permissions.role_id', $roleIds)
                        ->pluck('permissions.name')
                        ->unique()
                        ->values()
                        ->all();

                    $memberships[] = [
                        'outlet_id'   => $outlet->id,
                        'outlet_name' => $outlet->name,
                        'schema'      => $outlet->schema_name,
                        'roles'       => $roles->map(fn($r) => ['name' => $r->name, 'display_name' => $r->display_name])->values(),
                        'permissions' => $permissions,
                    ];
                } catch (\Throwable $e) {
                    // Schema may not exist yet — skip silently
                    continue;
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal — frontend falls back to empty memberships
        } finally {
            DB::statement('SET search_path TO public');
        }

        return $memberships;
    }
}
