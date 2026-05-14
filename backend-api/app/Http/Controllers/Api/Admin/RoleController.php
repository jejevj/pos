<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{
    #[OA\Get(
        path: "/api/admin/roles",
        tags: ["Admin - Roles"],
        summary: "Get all roles",
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(response: 200, description: "List of roles")
        ]
    )]
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }

    #[OA\Post(
        path: "/api/admin/roles",
        tags: ["Admin - Roles"],
        summary: "Create new role",
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "display_name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "manager"),
                    new OA\Property(property: "display_name", type: "string", example: "Manager"),
                    new OA\Property(property: "description", type: "string", example: "Manager role"),
                    new OA\Property(property: "is_active", type: "boolean", example: true),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "integer"))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Role created")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles|max:255',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create($request->only(['name', 'display_name', 'description', 'is_active']));

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role->load('permissions')
        ], 201);
    }

    #[OA\Get(
        path: "/api/admin/roles/{id}",
        tags: ["Admin - Roles"],
        summary: "Get role by ID",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Role details")
        ]
    )]
    public function show($id)
    {
        $role = Role::with('permissions', 'users')->findOrFail($id);
        return response()->json($role);
    }

    #[OA\Put(
        path: "/api/admin/roles/{id}",
        tags: ["Admin - Roles"],
        summary: "Update role",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "display_name", type: "string"),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "is_active", type: "boolean"),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "integer"))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Role updated")
        ]
    )]
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|unique:roles,name,' . $id . '|max:255',
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update($request->only(['name', 'display_name', 'description', 'is_active']));

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions')
        ]);
    }

    #[OA\Delete(
        path: "/api/admin/roles/{id}",
        tags: ["Admin - Roles"],
        summary: "Delete role",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Role deleted")
        ]
    )]
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Prevent deleting superadmin role
        if ($role->name === 'superadmin') {
            return response()->json([
                'message' => 'Cannot delete superadmin role'
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully'
        ]);
    }

    #[OA\Post(
        path: "/api/admin/roles/{id}/permissions",
        tags: ["Admin - Roles"],
        summary: "Assign permissions to role",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["permissions"],
                properties: [
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "integer"))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Permissions assigned")
        ]
    )]
    public function assignPermissions(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->sync($request->permissions);

        return response()->json([
            'message' => 'Permissions assigned successfully',
            'role' => $role->load('permissions')
        ]);
    }
}
