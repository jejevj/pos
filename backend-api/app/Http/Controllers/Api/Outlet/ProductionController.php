<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    use AuthorizesOutletAccess;

    // ── Units ──────────────────────────────────────────────────

    public function indexUnits(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $units = DB::table('production_units')->orderBy('nama')->get();
            DB::statement("SET search_path TO public");
            return response()->json(['data' => $units]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function storeUnit(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $data = $request->validate([
            'nama'      => 'required|string|max:150',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $id = DB::table('production_units')->insertGetId([
                'nama'       => $data['nama'],
                'deskripsi'  => $data['deskripsi'] ?? null,
                'is_active'  => $request->boolean('is_active', true),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $unit = DB::table('production_units')->find($id);
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Unit produksi dibuat', 'data' => $unit], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateUnit(Request $request, $outletId, $unitId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $data = $request->validate([
            'nama'      => 'sometimes|string|max:150',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            DB::table('production_units')->where('id', $unitId)->update(array_merge($data, ['updated_at' => now()]));
            $unit = DB::table('production_units')->find($unitId);
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Unit produksi diperbarui', 'data' => $unit]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroyUnit(Request $request, $outletId, $unitId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            DB::table('production_units')->where('id', $unitId)->delete();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Unit produksi dihapus']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ── Orders ─────────────────────────────────────────────────

    public function indexOrders(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $query = DB::table('production_orders as po')
                ->join('production_units as pu', 'po.unit_id', '=', 'pu.id')
                ->select('po.*', 'pu.nama as unit_nama');

            if ($request->filled('status')) {
                $query->where('po.status', $request->status);
            }
            if ($request->filled('unit_id')) {
                $query->where('po.unit_id', $request->unit_id);
            }

            $orders = $query->orderByDesc('po.created_at')->paginate(20);

            $orders->getCollection()->transform(function ($order) {
                $order->items = DB::table('production_order_items as poi')
                    ->leftJoin('bahan_baku as bb', 'poi.bahan_baku_id', '=', 'bb.id')
                    ->leftJoin('satuan as s', 'poi.satuan_id', '=', 's.id')
                    ->leftJoin('locations as l', 'poi.location_id', '=', 'l.id')
                    ->where('poi.order_id', $order->id)
                    ->select(
                        'poi.*',
                        'bb.nama as bahan_baku_nama',
                        's.nama as satuan_nama',
                        'l.name as location_nama'
                    )
                    ->get();
                return $order;
            });

            DB::statement("SET search_path TO public");
            return response()->json($orders);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function storeOrder(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $data = $request->validate([
            'unit_id'                => 'required|integer',
            'notes'                  => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.bahan_baku_id'  => 'required|integer',
            'items.*.quantity_planned' => 'required|numeric|min:0.001',
            'items.*.satuan_id'      => 'nullable|integer',
            'items.*.location_id'    => 'nullable|integer',
            'items.*.notes'          => 'nullable|string',
        ]);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $orderId = DB::transaction(function () use ($data) {
                $oid = DB::table('production_orders')->insertGetId([
                    'unit_id'    => $data['unit_id'],
                    'status'     => 'draft',
                    'notes'      => $data['notes'] ?? null,
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($data['items'] as $item) {
                    DB::table('production_order_items')->insert([
                        'order_id'          => $oid,
                        'bahan_baku_id'     => $item['bahan_baku_id'],
                        'quantity_planned'  => $item['quantity_planned'],
                        'satuan_id'         => $item['satuan_id'] ?? null,
                        'location_id'       => $item['location_id'] ?? null,
                        'notes'             => $item['notes'] ?? null,
                    ]);
                }
                return $oid;
            });

            $order = DB::table('production_orders')->find($orderId);
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Pesanan produksi dibuat', 'data' => $order], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateOrderStatus(Request $request, $outletId, $orderId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $data = $request->validate([
            'status' => 'required|in:draft,in_progress,cancelled',
        ]);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            DB::table('production_orders')->where('id', $orderId)->update([
                'status'     => $data['status'],
                'updated_at' => now(),
            ]);
            $order = DB::table('production_orders')->find($orderId);
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Status diperbarui', 'data' => $order]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function completeOrder(Request $request, $outletId, $orderId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $data = $request->validate([
            'items'                    => 'required|array|min:1',
            'items.*.id'               => 'required|integer',
            'items.*.quantity_actual'  => 'required|numeric|min:0',
            'items.*.location_id'      => 'required|integer',
        ]);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $order = DB::table('production_orders')->where('id', $orderId)->first();
            if (!$order) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
            }
            if ($order->status === 'completed') {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Pesanan sudah selesai'], 422);
            }

            DB::transaction(function () use ($orderId, $data) {
                foreach ($data['items'] as $item) {
                    DB::table('production_order_items')
                        ->where('id', $item['id'])
                        ->update([
                            'quantity_actual' => $item['quantity_actual'],
                            'location_id'     => $item['location_id'],
                        ]);

                    $poi = DB::table('production_order_items')->find($item['id']);
                    if (!$poi || $item['quantity_actual'] <= 0) {
                        continue;
                    }

                    $bb = DB::table('bahan_baku')->where('id', $poi->bahan_baku_id)->lockForUpdate()->first();
                    if (!$bb) {
                        continue;
                    }

                    $stockBefore = (float) ($bb->current_stock ?? 0);
                    $stockAfter  = $stockBefore + (float) $item['quantity_actual'];

                    DB::table('bahan_baku')
                        ->where('id', $poi->bahan_baku_id)
                        ->update([
                            'current_stock' => $stockAfter,
                            'updated_at'    => now(),
                        ]);

                    DB::table('stock_history')->insert([
                        'bahan_baku_id'  => $poi->bahan_baku_id,
                        'tipe'           => 'production',
                        'quantity'       => $item['quantity_actual'],
                        'stock_before'   => $stockBefore,
                        'stock_after'    => $stockAfter,
                        'reference_type' => 'production_order',
                        'reference_id'   => $orderId,
                        'notes'          => "Produksi dari order #{$orderId}",
                        'created_by'     => Auth::id(),
                        'created_at'     => now(),
                    ]);
                }

                DB::table('production_orders')->where('id', $orderId)->update([
                    'status'       => 'completed',
                    'completed_by' => Auth::id(),
                    'completed_at' => now(),
                    'updated_at'   => now(),
                ]);
            });

            $order = DB::table('production_orders')->find($orderId);
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Produksi selesai', 'data' => $order]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ── Stock History Report ───────────────────────────────────

    public function stockHistory(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $query = DB::table('stock_history as sh')
                ->join('bahan_baku as bb', 'sh.bahan_baku_id', '=', 'bb.id')
                ->select('sh.*', 'bb.nama as bahan_baku_nama');

            if ($request->filled('tipe')) {
                $query->where('sh.tipe', $request->tipe);
            }
            if ($request->filled('bahan_baku_id')) {
                $query->where('sh.bahan_baku_id', $request->bahan_baku_id);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('sh.created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('sh.created_at', '<=', $request->date_to);
            }

            $rows = $query->orderByDesc('sh.created_at')->paginate(30);
            DB::statement("SET search_path TO public");
            return response()->json($rows);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
