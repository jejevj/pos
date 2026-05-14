<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MenuController extends Controller
{
    #[OA\Get(
        path: "/api/admin/menus",
        tags: ["Admin - Menus"],
        summary: "Get all menus",
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(response: 200, description: "List of menus with hierarchy")
        ]
    )]
    public function index()
    {
        $menus = Menu::with(['children', 'permissions'])
            ->root()
            ->orderBy('order')
            ->get();

        return response()->json($menus);
    }

    #[OA\Post(
        path: "/api/admin/menus",
        tags: ["Admin - Menus"],
        summary: "Create new menu",
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "title"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "dashboard"),
                    new OA\Property(property: "title", type: "string", example: "Dashboard"),
                    new OA\Property(property: "icon", type: "string", example: "dashboard"),
                    new OA\Property(property: "route", type: "string", example: "/dashboard"),
                    new OA\Property(property: "url", type: "string", example: "/dashboard"),
                    new OA\Property(property: "parent_id", type: "integer", example: null),
                    new OA\Property(property: "order", type: "integer", example: 1),
                    new OA\Property(property: "is_active", type: "boolean", example: true),
                    new OA\Property(property: "meta", type: "object"),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "integer"))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Menu created")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:menus,id',
            'order' => 'integer',
            'is_active' => 'boolean',
            'meta' => 'nullable|array',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $menu = Menu::create($request->except('permissions'));

        if ($request->has('permissions')) {
            $menu->permissions()->sync($request->permissions);
        }

        return response()->json([
            'message' => 'Menu created successfully',
            'menu' => $menu->load('permissions')
        ], 201);
    }

    #[OA\Get(
        path: "/api/admin/menus/{id}",
        tags: ["Admin - Menus"],
        summary: "Get menu by ID",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Menu details")
        ]
    )]
    public function show($id)
    {
        $menu = Menu::with(['parent', 'children', 'permissions'])->findOrFail($id);
        return response()->json($menu);
    }

    #[OA\Put(
        path: "/api/admin/menus/{id}",
        tags: ["Admin - Menus"],
        summary: "Update menu",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "title", type: "string"),
                    new OA\Property(property: "icon", type: "string"),
                    new OA\Property(property: "route", type: "string"),
                    new OA\Property(property: "url", type: "string"),
                    new OA\Property(property: "parent_id", type: "integer"),
                    new OA\Property(property: "order", type: "integer"),
                    new OA\Property(property: "is_active", type: "boolean"),
                    new OA\Property(property: "meta", type: "object"),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "integer"))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Menu updated")
        ]
    )]
    public function update(Request $request, $id)
    {
        $menu = Menu::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:255',
            'icon' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:menus,id',
            'order' => 'integer',
            'is_active' => 'boolean',
            'meta' => 'nullable|array',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $menu->update($request->except('permissions'));

        if ($request->has('permissions')) {
            $menu->permissions()->sync($request->permissions);
        }

        return response()->json([
            'message' => 'Menu updated successfully',
            'menu' => $menu->load('permissions')
        ]);
    }

    #[OA\Delete(
        path: "/api/admin/menus/{id}",
        tags: ["Admin - Menus"],
        summary: "Delete menu",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Menu deleted")
        ]
    )]
    public function destroy($id)
    {
        $menu = Menu::findOrFail($id);
        $menu->delete();

        return response()->json([
            'message' => 'Menu deleted successfully'
        ]);
    }

    #[OA\Get(
        path: "/api/menus/user",
        tags: ["Menus"],
        summary: "Get user accessible menus",
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(response: 200, description: "User menus based on permissions")
        ]
    )]
    public function userMenus(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            // Superadmin gets all menus
            $menus = Menu::with('children')
                ->active()
                ->root()
                ->orderBy('order')
                ->get();
        } else {
            // Get user permissions
            $userPermissions = $user->getAllPermissions()->pluck('id');

            // Get menus that user has permission to access
            $menus = Menu::with('children')
                ->active()
                ->root()
                ->where(function ($query) use ($userPermissions) {
                    $query->whereHas('permissions', function ($q) use ($userPermissions) {
                        $q->whereIn('permissions.id', $userPermissions);
                    })->orWhereDoesntHave('permissions');
                })
                ->orderBy('order')
                ->get();
        }

        return response()->json($menus);
    }
}
