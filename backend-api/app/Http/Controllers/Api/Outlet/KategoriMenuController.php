<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\KategoriMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KategoriMenuController extends Controller
{
    use AuthorizesOutletAccess;


    public function index($outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $data = KategoriMenu::withCount('menu')->orderBy('urutan')->orderBy('nama')->get();
            DB::statement("SET search_path TO public");
            return response()->json($data);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $validator = Validator::make($request->all(), [
            'nama'     => 'required|string|max:100',
            'deskripsi'=> 'nullable|string',
            'urutan'   => 'nullable|integer',
            'is_active'=> 'boolean',
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $data = KategoriMenu::create($validator->validated());
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Created successfully', 'data' => $data], 201);
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
            $data = KategoriMenu::withCount('menu')->findOrFail($id);
            DB::statement("SET search_path TO public");
            return response()->json($data);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $validator = Validator::make($request->all(), [
            'nama'     => 'string|max:100',
            'deskripsi'=> 'nullable|string',
            'urutan'   => 'nullable|integer',
            'is_active'=> 'boolean',
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $data = KategoriMenu::findOrFail($id);
            $data->update($validator->validated());
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Updated successfully', 'data' => $data]);
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
            $data = KategoriMenu::findOrFail($id);
            if ($data->menu()->count() > 0) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Cannot delete category with existing menus'], 400);
            }
            $data->delete();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Deleted successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
