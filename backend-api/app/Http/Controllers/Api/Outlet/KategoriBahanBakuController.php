<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\KategoriBahanBaku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Bahan Baku - Kategori",
 *     description="Raw material category management"
 * )
 */
class KategoriBahanBakuController extends Controller
{
    use AuthorizesOutletAccess;

    /**
     * @OA\Get(
     *     path="/api/outlets/{outlet}/kategori-bahan-baku",
     *     summary="Get all categories",
     *     tags={"Bahan Baku - Kategori"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="outlet", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Outlet not found")
     * )
     */
    public function index($outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            // Switch to outlet schema
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $categories = KategoriBahanBaku::withCount('bahanBaku')
                ->orderBy('nama')
                ->get();
            
            // Reset schema
            DB::statement("SET search_path TO public");

            return response()->json($categories);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/outlets/{outlet}/kategori-bahan-baku",
     *     summary="Create new category",
     *     tags={"Bahan Baku - Kategori"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="outlet", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama"},
     *             @OA\Property(property="nama", type="string", example="Sayuran"),
     *             @OA\Property(property="deskripsi", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $category = KategoriBahanBaku::create($validator->validated());
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to create category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific category
     */
    public function show($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $category = KategoriBahanBaku::withCount('bahanBaku')->find($id);
            
            DB::statement("SET search_path TO public");

            if (!$category) {
                return response()->json(['message' => 'Category not found'], 404);
            }

            return response()->json($category);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to fetch category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a category
     */
    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            'nama' => 'string|max:100',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $category = KategoriBahanBaku::find($id);
            
            if (!$category) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Category not found'], 404);
            }

            $category->update($validator->validated());
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Category updated successfully',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to update category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a category
     */
    public function destroy($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $category = KategoriBahanBaku::find($id);
            
            if (!$category) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Category not found'], 404);
            }

            // Check if category has bahan baku
            $bahanBakuCount = $category->bahanBaku()->count();
            if ($bahanBakuCount > 0) {
                DB::statement("SET search_path TO public");
                return response()->json([
                    'message' => 'Cannot delete category with existing bahan baku',
                    'bahan_baku_count' => $bahanBakuCount
                ], 400);
            }

            $category->delete();
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
