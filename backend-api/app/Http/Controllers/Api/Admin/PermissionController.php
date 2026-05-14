<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PermissionController extends Controller
{
    #[OA\Get(
        path: "/api/admin/permissions",
        tags: ["Admin - Permissions"],
        summary: "Get all permissions",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "group",
                in: "query",
                description: "Filter by group",
                required: false,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "List of permissions")
        ]
    )]
    public function index(Request $request)
    {
        $query = Permission::query();

        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        $permissions = $query->orderBy('group')->orderBy('name')->get();

        // Group by group
        $grouped = $permissions->groupBy('group');

        return response()->json([
            'permissions' => $permissions,
            'grouped' => $grouped
        ]);
    }

    #[OA\Post(
        path: "/api/admin/permissions",
        tags: ["Admin - Permissions"],
        summary: "Create new permission",
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "display_name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "users.create"),
                    new OA\Property(property: "display_name", type: "string", example: "Create Users"),
                    new OA\Property(property: "group", type: "string", example: "users"),
                    new OA\Property(property: "description", type: "string", example: "Permission to create users")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Permission created")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions|max:255',
            'display_name' => 'required|string|max:255',
            'group' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $permission = Permission::create($request->all());

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => $permission
        ], 201);
    }

    #[OA\Get(
        path: "/api/admin/permissions/{id}",
        tags: ["Admin - Permissions"],
        summary: "Get permission by ID",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Permission details")
        ]
    )]
    public function show($id)
    {
        $permission = Permission::with('roles')->findOrFail($id);
        return response()->json($permission);
    }

    #[OA\Put(
        path: "/api/admin/permissions/{id}",
        tags: ["Admin - Permissions"],
        summary: "Update permission",
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
                    new OA\Property(property: "group", type: "string"),
                    new OA\Property(property: "description", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Permission updated")
        ]
    )]
    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|unique:permissions,name,' . $id . '|max:255',
            'display_name' => 'sometimes|string|max:255',
            'group' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $permission->update($request->all());

        return response()->json([
            'message' => 'Permission updated successfully',
            'permission' => $permission
        ]);
    }

    #[OA\Delete(
        path: "/api/admin/permissions/{id}",
        tags: ["Admin - Permissions"],
        summary: "Delete permission",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Permission deleted")
        ]
    )]
    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully'
        ]);
    }
}
