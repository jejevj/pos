<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Satuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SatuanController extends Controller
{
    /**
     * Get all units for an outlet
     */
    public function index($outletId)
    {
        $user = Auth::user();
        $outlet = Outlet::find($outletId);

        if (!$outlet) {
            return response()->json(['message' => 'Outlet not found'], 404);
        }

        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $units = Satuan::orderBy('tipe')->orderBy('nama')->get();
            
            DB::statement("SET search_path TO public");

            return response()->json($units);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to fetch units',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new unit
     */
    public function store(Request $request, $outletId)
    {
        $user = Auth::user();
        $outlet = Outlet::find($outletId);

        if (!$outlet) {
            return response()->json(['message' => 'Outlet not found'], 404);
        }

        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:50',
            'singkatan' => 'required|string|max:10',
            'tipe' => 'required|string|in:weight,volume,count',
            'is_base_unit' => 'boolean',
            'conversion_to_base' => 'nullable|numeric|min:0',
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
            
            $unit = Satuan::create($validator->validated());
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Unit created successfully',
                'data' => $unit
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to create unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific unit
     */
    public function show($outletId, $id)
    {
        $user = Auth::user();
        $outlet = Outlet::find($outletId);

        if (!$outlet) {
            return response()->json(['message' => 'Outlet not found'], 404);
        }

        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $unit = Satuan::find($id);
            
            DB::statement("SET search_path TO public");

            if (!$unit) {
                return response()->json(['message' => 'Unit not found'], 404);
            }

            return response()->json($unit);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to fetch unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a unit
     */
    public function update(Request $request, $outletId, $id)
    {
        $user = Auth::user();
        $outlet = Outlet::find($outletId);

        if (!$outlet) {
            return response()->json(['message' => 'Outlet not found'], 404);
        }

        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'string|max:50',
            'singkatan' => 'string|max:10',
            'tipe' => 'string|in:weight,volume,count',
            'is_base_unit' => 'boolean',
            'conversion_to_base' => 'nullable|numeric|min:0',
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
            
            $unit = Satuan::find($id);
            
            if (!$unit) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Unit not found'], 404);
            }

            $unit->update($validator->validated());
            
            DB::statement("SET search_path TO public");
 
            return response()->json([
                'message' => 'Unit updated successfully',
                'data' => $unit
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to update unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a unit
     */
    public function destroy($outletId, $id)
    {
        $user = Auth::user();
        $outlet = Outlet::find($outletId);

        if (!$outlet) {
            return response()->json(['message' => 'Outlet not found'], 404);
        }

        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $unit = Satuan::find($id);
            
            if (!$unit) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Unit not found'], 404);
            }

            $unit->delete();
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Unit deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to delete unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
