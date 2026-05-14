<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StationController extends Controller
{
    private function authorizeOutlet($outletId)
    {
        $user = Auth::user();
        $outlet = Outlet::find($outletId);
        if (!$outlet) abort(404, 'Outlet not found');
        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) abort(403, 'Unauthorized');
        return $outlet;
    }

    public function index($outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        DB::statement("SET search_path TO {$outlet->schema_name}, public");
        $stations = Station::orderBy('urutan')->orderBy('nama')->get();
        DB::statement("SET search_path TO public");
        return response()->json($stations);
    }

    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $request->validate([
            'nama'     => 'required|string|max:100',
            'deskripsi'=> 'nullable|string',
            'warna'    => 'nullable|string|max:20',
            'icon'     => 'nullable|string|max:50',
            'urutan'   => 'nullable|integer',
        ]);

        DB::statement("SET search_path TO {$outlet->schema_name}, public");
        $station = Station::create([
            'nama'      => $request->nama,
            'deskripsi' => $request->deskripsi,
            'warna'     => $request->warna ?? '#3b82f6',
            'icon'      => $request->icon ?? 'pi pi-box',
            'urutan'    => $request->urutan ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);
        DB::statement("SET search_path TO public");
        return response()->json(['message' => 'Station created', 'data' => $station], 201);
    }

    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $request->validate([
            'nama'     => 'required|string|max:100',
            'deskripsi'=> 'nullable|string',
            'warna'    => 'nullable|string|max:20',
            'icon'     => 'nullable|string|max:50',
            'urutan'   => 'nullable|integer',
        ]);

        DB::statement("SET search_path TO {$outlet->schema_name}, public");
        $station = Station::findOrFail($id);
        $station->update([
            'nama'      => $request->nama,
            'deskripsi' => $request->deskripsi,
            'warna'     => $request->warna ?? $station->warna,
            'icon'      => $request->icon ?? $station->icon,
            'urutan'    => $request->urutan ?? $station->urutan,
            'is_active' => $request->has('is_active') ? $request->is_active : $station->is_active,
        ]);
        DB::statement("SET search_path TO public");
        return response()->json(['message' => 'Station updated', 'data' => $station]);
    }

    public function destroy($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        DB::statement("SET search_path TO {$outlet->schema_name}, public");
        $station = Station::findOrFail($id);
        // Unassign menu items from this station
        DB::statement("UPDATE menu SET station_id = NULL WHERE station_id = ?", [$id]);
        $station->delete();
        DB::statement("SET search_path TO public");
        return response()->json(['message' => 'Station deleted']);
    }

    /**
    /**
     * Get active orders for a specific station (KDS view)
     * Shows orders in draft OR paid status that have items not yet served.
     * Orders enter KDS as soon as they are created (draft), so kitchen
     * can start preparing before payment is processed.
     */
    public function orders(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        DB::statement("SET search_path TO {$outlet->schema_name}, public");

        $station = Station::findOrFail($id);

        // Show orders (draft or paid) that still have unserved items for this station
        $orders = DB::select("
            SELECT DISTINCT
                o.id, o.kode, o.order_type, o.table_number, o.customer_name,
                o.status, o.kitchen_status, o.created_at, o.notes
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            INNER JOIN menu m ON m.id = oi.menu_id
            WHERE m.station_id = ?
              AND o.status IN ('draft', 'paid')
              AND o.deleted_at IS NULL
              AND oi.status IN ('pending', 'preparing', 'ready')
            ORDER BY o.created_at ASC
        ", [$id]);

        // For each order, load only items belonging to this station
        foreach ($orders as $order) {
            $order->items = DB::select("
                SELECT oi.id, oi.menu_id, oi.menu_name, oi.quantity, oi.notes,
                       oi.status, oi.confirmed_at, m.station_id
                FROM order_items oi
                INNER JOIN menu m ON m.id = oi.menu_id
                WHERE oi.order_id = ? AND m.station_id = ?
                ORDER BY oi.id ASC
            ", [$order->id, $id]);
        }

        DB::statement("SET search_path TO public");

        return response()->json([
            'station' => $station,
            'orders'  => $orders,
        ]);
    }

    /**
     * Confirm an order item as ready (by station staff)
     */
    public function confirmItem(Request $request, $outletId, $stationId, $itemId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        DB::statement("SET search_path TO {$outlet->schema_name}, public");

        // Update item status to 'ready'
        DB::statement("
            UPDATE order_items
            SET status = 'ready', confirmed_at = NOW(), ready_at = NOW(), confirmed_by = ?
            WHERE id = ?
        ", [Auth::id(), $itemId]);

        // Check if ALL items in this order (across all stations) are ready
        $orderId = DB::selectOne("SELECT order_id FROM order_items WHERE id = ?", [$itemId])?->order_id;

        if ($orderId) {
            $pendingItems = DB::selectOne("
                SELECT COUNT(*) as cnt FROM order_items oi
                INNER JOIN menu m ON m.id = oi.menu_id
                WHERE oi.order_id = ? AND m.station_id IS NOT NULL AND oi.status != 'ready'
            ", [$orderId]);

            if ($pendingItems && $pendingItems->cnt == 0) {
                DB::statement("UPDATE orders SET kitchen_status = 'ready' WHERE id = ?", [$orderId]);
            }
        }

        DB::statement("SET search_path TO public");

        return response()->json(['message' => 'Item confirmed as ready']);
    }

    /**
     * Mark order as served (all items delivered to customer)
     * This removes the order from KDS view
     */
    public function serveOrder(Request $request, $outletId, $stationId, $orderId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        DB::statement("SET search_path TO {$outlet->schema_name}, public");

        // Mark all items of this station as 'served'
        DB::statement("
            UPDATE order_items oi
            SET status = 'served', served_at = NOW()
            FROM menu m
            WHERE oi.menu_id = m.id
              AND m.station_id = ?
              AND oi.order_id = ?
        ", [$stationId, $orderId]);

        // Check if ALL station items across all stations are served
        $pendingStationItems = DB::selectOne("
            SELECT COUNT(*) as cnt FROM order_items oi
            INNER JOIN menu m ON m.id = oi.menu_id
            WHERE oi.order_id = ? AND m.station_id IS NOT NULL AND oi.status != 'served'
        ", [$orderId]);

        if ($pendingStationItems && $pendingStationItems->cnt == 0) {
            DB::statement("UPDATE orders SET kitchen_status = 'served' WHERE id = ?", [$orderId]);
        }

        DB::statement("SET search_path TO public");

        return response()->json(['message' => 'Order marked as served']);
    }

    /**
     * Mark item as preparing
     */
    public function startItem(Request $request, $outletId, $stationId, $itemId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        DB::statement("SET search_path TO {$outlet->schema_name}, public");

        DB::statement("
            UPDATE order_items SET status = 'preparing', preparing_at = NOW() WHERE id = ?
        ", [$itemId]);

        // Update order kitchen_status to 'preparing' if still pending
        $orderId = DB::selectOne("SELECT order_id FROM order_items WHERE id = ?", [$itemId])?->order_id;
        if ($orderId) {
            DB::statement("
                UPDATE orders SET kitchen_status = 'preparing'
                WHERE id = ? AND kitchen_status = 'pending'
            ", [$orderId]);
        }

        DB::statement("SET search_path TO public");

        return response()->json(['message' => 'Item marked as preparing']);
    }
}
