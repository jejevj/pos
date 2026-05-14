<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    use AuthorizesOutletAccess;


    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $this->ensureTables();
            $query = DB::table('locations')->whereNull('deleted_at');
            if ($request->has('type'))      $query->where('type', $request->type);
            if ($request->has('is_active')) $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            $locations = $query->orderBy('display_order')->orderBy('name')->get();
            DB::statement("SET search_path TO public");
            return response()->json($locations);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $request->validate([
            'name'          => 'required|string|max:100',
            'type'          => 'required|in:warehouse,production,retail',
            'description'   => 'nullable|string',
            'is_active'     => 'boolean',
            'display_order' => 'integer|min:0',
        ]);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $this->ensureTables();
            $id = DB::table('locations')->insertGetId([
                'name'          => $request->name,
                'type'          => $request->type,
                'description'   => $request->description,
                'is_active'     => $request->boolean('is_active', true),
                'display_order' => $request->input('display_order', 99),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $location = DB::table('locations')->find($id);
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Location created', 'data' => $location], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $request->validate([
            'name'          => 'string|max:100',
            'type'          => 'in:warehouse,production,retail',
            'description'   => 'nullable|string',
            'is_active'     => 'boolean',
            'display_order' => 'integer|min:0',
        ]);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            DB::table('locations')->where('id', $id)->update(array_merge(
                $request->only(['name', 'type', 'description', 'is_active', 'display_order']),
                ['updated_at' => now()]
            ));
            $location = DB::table('locations')->find($id);
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Location updated', 'data' => $location]);
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
            $used = DB::table('bahan_baku_locations')->where('location_id', $id)->where('current_stock', '>', 0)->exists();
            if ($used) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Cannot delete — location has stock'], 422);
            }
            DB::table('locations')->where('id', $id)->update(['deleted_at' => now()]);
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Location deleted']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ── Stock per location ────────────────────────────────────────────────────

    public function getStock(Request $request, $outletId, $locationId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $stock = DB::table('bahan_baku_locations as bl')
                ->join('bahan_baku as bb', 'bl.bahan_baku_id', '=', 'bb.id')
                ->leftJoin('satuan as s', 'bb.satuan_id', '=', 's.id')
                ->leftJoin('kategori_bahan_baku as k', 'bb.kategori_id', '=', 'k.id')
                ->where('bl.location_id', $locationId)
                ->whereNull('bb.deleted_at')
                ->select('bl.*', 'bb.nama', 'bb.kode', 'bb.minimum_stock', 'bb.current_stock as total_stock',
                         's.nama as satuan_nama', 'k.nama as kategori_nama')
                ->get();
            DB::statement("SET search_path TO public");
            return response()->json($stock);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function ensureTables(): void
    {
        $schema = DB::getSchemaBuilder();

        if (!$schema->hasTable('locations')) {
            DB::statement("
                CREATE TABLE locations (
                    id BIGSERIAL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    type VARCHAR(30) NOT NULL DEFAULT 'warehouse',
                    description TEXT,
                    is_active BOOLEAN DEFAULT TRUE,
                    display_order INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW(),
                    deleted_at TIMESTAMP NULL
                )
            ");
            // Seed defaults
            DB::table('locations')->insert([
                ['name' => 'Gudang',       'type' => 'warehouse',  'display_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Kedai Utama',  'type' => 'retail',     'display_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Dapur',        'type' => 'production', 'display_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (!$schema->hasTable('bahan_baku_locations')) {
            DB::statement("
                CREATE TABLE bahan_baku_locations (
                    id BIGSERIAL PRIMARY KEY,
                    bahan_baku_id BIGINT NOT NULL,
                    location_id BIGINT NOT NULL,
                    current_stock DECIMAL(12,4) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW(),
                    UNIQUE(bahan_baku_id, location_id)
                )
            ");
        }

        if (!$schema->hasTable('stock_movements')) {
            DB::statement("
                CREATE TABLE stock_movements (
                    id BIGSERIAL PRIMARY KEY,
                    bahan_baku_id BIGINT NOT NULL,
                    from_location_id BIGINT NULL,
                    to_location_id BIGINT NULL,
                    type VARCHAR(30) NOT NULL,
                    quantity DECIMAL(12,4) NOT NULL,
                    notes TEXT,
                    reference_type VARCHAR(50),
                    reference_id BIGINT,
                    created_by BIGINT,
                    created_at TIMESTAMP DEFAULT NOW()
                )
            ");
        }
    }
}
