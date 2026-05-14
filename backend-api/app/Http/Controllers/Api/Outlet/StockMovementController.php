<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\BahanBaku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller
{
    private function authorizeOutlet($outletId)
    {
        $user   = Auth::user();
        $outlet = Outlet::find($outletId);
        if (!$outlet) abort(404, 'Outlet not found');
        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) abort(403, 'Unauthorized');
        return $outlet;
    }

    /**
     * List stock movements with filters
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $query = DB::table('stock_movements as sm')
                ->join('bahan_baku as bb', 'sm.bahan_baku_id', '=', 'bb.id')
                ->leftJoin('locations as fl', 'sm.from_location_id', '=', 'fl.id')
                ->leftJoin('locations as tl', 'sm.to_location_id', '=', 'tl.id')
                ->leftJoin('outlet_users as u', 'sm.created_by', '=', 'u.id')
                ->leftJoin('satuan as s', 'bb.satuan_id', '=', 's.id')
                ->select(
                    'sm.*',
                    'bb.nama as bahan_baku_nama', 'bb.kode as bahan_baku_kode',
                    's.nama as satuan_nama',
                    'fl.name as from_location_name',
                    'tl.name as to_location_name',
                    'u.name as created_by_name'
                )
                ->orderBy('sm.created_at', 'desc');

            if ($request->bahan_baku_id)   $query->where('sm.bahan_baku_id', $request->bahan_baku_id);
            if ($request->location_id)     $query->where(function($q) use ($request) {
                $q->where('sm.from_location_id', $request->location_id)
                  ->orWhere('sm.to_location_id', $request->location_id);
            });
            if ($request->type)            $query->where('sm.type', $request->type);
            if ($request->date_from)       $query->whereDate('sm.created_at', '>=', $request->date_from);
            if ($request->date_to)         $query->whereDate('sm.created_at', '<=', $request->date_to);

            $movements = $query->paginate($request->input('per_page', 50));
            DB::statement("SET search_path TO public");
            return response()->json($movements);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Stock IN — receive stock into a location
     */
    public function stockIn(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $request->validate([
            'bahan_baku_id' => 'required|integer',
            'to_location_id'=> 'required|integer',
            'quantity'      => 'required|numeric|min:0',
            'notes'         => 'nullable|string',
            'reference_type'=> 'nullable|string',
            'reference_id'  => 'nullable|integer',
        ]);
        try {
            DB::beginTransaction();
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            (new LocationController)->ensureTables();

            $this->adjustLocationStock($request->bahan_baku_id, $request->to_location_id, $request->quantity);
            $this->adjustGlobalStock($request->bahan_baku_id, $request->quantity, 'in');

            DB::table('stock_movements')->insert([
                'bahan_baku_id'  => $request->bahan_baku_id,
                'from_location_id' => null,
                'to_location_id' => $request->to_location_id,
                'type'           => 'in',
                'quantity'       => $request->quantity,
                'notes'          => $request->notes,
                'reference_type' => $request->reference_type,
                'reference_id'   => $request->reference_id,
                'created_by'     => Auth::id(),
                'created_at'     => now(),
            ]);

            DB::commit();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Stock in recorded']);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Stock OUT — consume stock from a location
     */
    public function stockOut(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $request->validate([
            'bahan_baku_id'    => 'required|integer',
            'from_location_id' => 'required|integer',
            'quantity'         => 'required|numeric|min:0',
            'notes'            => 'nullable|string',
        ]);
        try {
            DB::beginTransaction();
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            (new LocationController)->ensureTables();

            $locStock = $this->getLocationStock($request->bahan_baku_id, $request->from_location_id);
            if ($locStock < $request->quantity) {
                throw new \Exception("Stok di lokasi tidak cukup. Tersedia: {$locStock}");
            }

            $this->adjustLocationStock($request->bahan_baku_id, $request->from_location_id, -$request->quantity);
            $this->adjustGlobalStock($request->bahan_baku_id, $request->quantity, 'out');

            DB::table('stock_movements')->insert([
                'bahan_baku_id'    => $request->bahan_baku_id,
                'from_location_id' => $request->from_location_id,
                'to_location_id'   => null,
                'type'             => 'out',
                'quantity'         => $request->quantity,
                'notes'            => $request->notes,
                'created_by'       => Auth::id(),
                'created_at'       => now(),
            ]);

            DB::commit();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Stock out recorded']);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Transfer stock between locations
     */
    public function transfer(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $request->validate([
            'bahan_baku_id'    => 'required|integer',
            'from_location_id' => 'required|integer',
            'to_location_id'   => 'required|integer|different:from_location_id',
            'quantity'         => 'required|numeric|min:0',
            'notes'            => 'nullable|string',
        ]);
        try {
            DB::beginTransaction();
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            (new LocationController)->ensureTables();

            $locStock = $this->getLocationStock($request->bahan_baku_id, $request->from_location_id);
            if ($locStock < $request->quantity) {
                throw new \Exception("Stok di lokasi asal tidak cukup. Tersedia: {$locStock}");
            }

            $this->adjustLocationStock($request->bahan_baku_id, $request->from_location_id, -$request->quantity);
            $this->adjustLocationStock($request->bahan_baku_id, $request->to_location_id, $request->quantity);
            // Global stock unchanged on transfer

            DB::table('stock_movements')->insert([
                'bahan_baku_id'    => $request->bahan_baku_id,
                'from_location_id' => $request->from_location_id,
                'to_location_id'   => $request->to_location_id,
                'type'             => 'transfer',
                'quantity'         => $request->quantity,
                'notes'            => $request->notes,
                'created_by'       => Auth::id(),
                'created_at'       => now(),
            ]);

            DB::commit();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Transfer recorded']);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get stock summary per location for all bahan baku
     */
    public function summary(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            (new LocationController)->ensureTables();

            $data = DB::table('bahan_baku as bb')
                ->leftJoin('bahan_baku_locations as bl', 'bb.id', '=', 'bl.bahan_baku_id')
                ->leftJoin('locations as l', 'bl.location_id', '=', 'l.id')
                ->leftJoin('satuan as s', 'bb.satuan_id', '=', 's.id')
                ->leftJoin('kategori_bahan_baku as k', 'bb.kategori_id', '=', 'k.id')
                ->whereNull('bb.deleted_at')
                ->where('bb.is_active', true)
                ->select(
                    'bb.id', 'bb.kode', 'bb.nama', 'bb.current_stock', 'bb.minimum_stock',
                    's.nama as satuan_nama', 'k.nama as kategori_nama',
                    'bl.location_id', 'bl.current_stock as location_stock',
                    'l.name as location_name', 'l.type as location_type'
                )
                ->orderBy('bb.nama')
                ->get();

            // Group by bahan_baku
            $grouped = $data->groupBy('id')->map(function ($rows) {
                $first = $rows->first();
                return [
                    'id'            => $first->id,
                    'kode'          => $first->kode,
                    'nama'          => $first->nama,
                    'current_stock' => $first->current_stock,
                    'minimum_stock' => $first->minimum_stock,
                    'satuan_nama'   => $first->satuan_nama,
                    'kategori_nama' => $first->kategori_nama,
                    'locations'     => $rows->filter(fn($r) => $r->location_id)
                        ->map(fn($r) => [
                            'location_id'    => $r->location_id,
                            'location_name'  => $r->location_name,
                            'location_type'  => $r->location_type,
                            'current_stock'  => $r->location_stock,
                        ])->values(),
                ];
            })->values();

            DB::statement("SET search_path TO public");
            return response()->json($grouped);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getLocationStock(int $bahanBakuId, int $locationId): float
    {
        $row = DB::table('bahan_baku_locations')
            ->where('bahan_baku_id', $bahanBakuId)
            ->where('location_id', $locationId)
            ->first();
        return $row ? (float) $row->current_stock : 0;
    }

    private function adjustLocationStock(int $bahanBakuId, int $locationId, float $delta): void
    {
        $exists = DB::table('bahan_baku_locations')
            ->where('bahan_baku_id', $bahanBakuId)
            ->where('location_id', $locationId)
            ->exists();

        if ($exists) {
            DB::table('bahan_baku_locations')
                ->where('bahan_baku_id', $bahanBakuId)
                ->where('location_id', $locationId)
                ->update([
                    'current_stock' => DB::raw("current_stock + {$delta}"),
                    'updated_at'    => now(),
                ]);
        } else {
            DB::table('bahan_baku_locations')->insert([
                'bahan_baku_id' => $bahanBakuId,
                'location_id'   => $locationId,
                'current_stock' => max(0, $delta),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    private function adjustGlobalStock(int $bahanBakuId, float $quantity, string $direction): void
    {
        $bb = BahanBaku::find($bahanBakuId);
        if (!$bb) return;
        if ($direction === 'in') {
            $bb->current_stock += $quantity;
        } else {
            $bb->current_stock = max(0, $bb->current_stock - $quantity);
        }
        $bb->save();
    }
}
