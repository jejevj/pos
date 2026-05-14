<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    /**
     * Get all suppliers for an outlet
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
            
            $suppliers = Supplier::orderBy('nama')->get();
            
            DB::statement("SET search_path TO public");

            return response()->json($suppliers);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to fetch suppliers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new supplier
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
            'nama' => 'required|string|max:200',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'alamat' => 'nullable|string',
            'kota' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'kode_pos' => 'nullable|string|max:10',
            'payment_terms' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
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
            
            $data = $validator->validated();
            $data['kode'] = Supplier::generateKode($outletId);
            
            $supplier = Supplier::create($data);
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Supplier created successfully',
                'data' => $supplier
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to create supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific supplier
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
            
            $supplier = Supplier::find($id);
            
            DB::statement("SET search_path TO public");

            if (!$supplier) {
                return response()->json(['message' => 'Supplier not found'], 404);
            }

            return response()->json($supplier);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to fetch supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a supplier
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
            'nama' => 'string|max:200',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'alamat' => 'nullable|string',
            'kota' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'kode_pos' => 'nullable|string|max:10',
            'payment_terms' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
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
            
            $supplier = Supplier::find($id);
            
            if (!$supplier) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Supplier not found'], 404);
            }

            $supplier->update($validator->validated());
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Supplier updated successfully',
                'data' => $supplier
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to update supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a supplier
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
            
            $supplier = Supplier::find($id);
            
            if (!$supplier) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Supplier not found'], 404);
            }

            $supplier->delete();
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Supplier deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to delete supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
