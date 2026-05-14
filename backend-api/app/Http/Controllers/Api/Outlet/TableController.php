<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TableController extends Controller
{
    private function authorizeOutlet($outletId)
    {
        $user = Auth::user();
        $outlet = Outlet::find($outletId);
        if (!$outlet) abort(404, 'Outlet not found');
        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) abort(403, 'Unauthorized');
        return $outlet;
    }

    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $query = Table::query();
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('area')) {
                $query->where('area', $request->area);
            }
            
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }
            
            $tables = $query->orderBy('table_number')->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($tables);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'table_number' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'area' => 'required|string|in:indoor,outdoor,vip',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $table = Table::create([
                'table_number' => $request->table_number,
                'capacity' => $request->capacity,
                'area' => $request->area,
                'status' => 'available',
                'is_active' => true,
            ]);
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Table created successfully', 'data' => $table], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $table = Table::findOrFail($id);
            
            DB::statement("SET search_path TO public");
            
            return response()->json($table);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'table_number' => 'sometimes|string|max:50',
            'capacity' => 'sometimes|integer|min:1',
            'area' => 'sometimes|string|in:indoor,outdoor,vip',
            'status' => 'sometimes|string|in:available,occupied,reserved',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $table = Table::findOrFail($id);
            $table->update($request->only(['table_number', 'capacity', 'area', 'status', 'is_active']));
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Table updated successfully', 'data' => $table]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $table = Table::findOrFail($id);
            $table->delete();
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Table deleted successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cleanup table - mark as available
     */
    public function cleanup($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $table = Table::findOrFail($id);
            $table->markAsAvailable();
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Table cleaned up successfully', 'data' => $table]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
