<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Jobs\SendOrderProgressWhatsApp;
use App\Models\Outlet;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StationController extends Controller
{
    use AuthorizesOutletAccess;


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

        // Show orders that have items for this station OR items with no station assigned.
        // Items without station_id are shown on all stations (fallback).
        $orders = DB::select("
            SELECT DISTINCT
                o.id, o.kode, o.order_type, o.table_number, o.customer_name,
                o.status, o.kitchen_status, o.created_at, o.notes
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            INNER JOIN menu m ON m.id = oi.menu_id
            WHERE (m.station_id = ? OR m.station_id IS NULL)
              AND o.status IN ('draft', 'paid', 'bon')
              AND o.deleted_at IS NULL
              AND oi.status IN ('pending', 'preparing', 'ready')
              AND (
                    COALESCE(o.source, 'pos') = 'pos'
                 OR o.approval_status = 'approved'
              )
            ORDER BY o.created_at ASC
        ", [$id]);

        // For each order, load items for this station + items without station
        foreach ($orders as $order) {
            $order->items = DB::select("
                SELECT oi.id, oi.menu_id, oi.menu_name, oi.quantity, oi.notes,
                       oi.status, oi.confirmed_at, m.station_id
                FROM order_items oi
                INNER JOIN menu m ON m.id = oi.menu_id
                WHERE oi.order_id = ? AND (m.station_id = ? OR m.station_id IS NULL)
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
        $this->ensureNotificationColumns();

        // Update item status to 'ready'
        // Use PHP now() (respects APP_TIMEZONE) instead of SQL NOW() to keep
        // timestamps in the same timezone as orders.created_at (written by Eloquent).
        $now = \Carbon\Carbon::now();
        DB::statement("
            UPDATE order_items
            SET status = 'ready', confirmed_at = ?, ready_at = ?, confirmed_by = ?
            WHERE id = ?
        ", [$now, $now, Auth::id(), $itemId]);

        // Check if ALL items in this order (across all stations) are ready
        $orderId = DB::selectOne("SELECT order_id FROM order_items WHERE id = ?", [$itemId])?->order_id;
        $dispatchReady = false;

        if ($orderId) {
            $pendingItems = DB::selectOne("
                SELECT COUNT(*) as cnt FROM order_items oi
                INNER JOIN menu m ON m.id = oi.menu_id
                WHERE oi.order_id = ? AND m.station_id IS NOT NULL AND oi.status != 'ready'
            ", [$orderId]);

            if ($pendingItems && $pendingItems->cnt == 0) {
                // Atomically claim the "ready" notification slot so concurrent
                // confirmItem calls from kitchen+bar do not both dispatch.
                $affected = DB::update("
                    UPDATE orders
                       SET kitchen_status = 'ready',
                           wa_ready_notified_at = ?
                     WHERE id = ?
                       AND wa_ready_notified_at IS NULL
                ", [$now, $orderId]);

                if ($affected > 0) {
                    $dispatchReady = true;
                } else {
                    // Already notified — still ensure kitchen_status is set
                    DB::statement("UPDATE orders SET kitchen_status = 'ready' WHERE id = ?", [$orderId]);
                }
            }
        }

        DB::statement("SET search_path TO public");

        if ($dispatchReady && $orderId) {
            $this->dispatchProgressNotification($outlet, (int) $orderId, 'ready');
        }

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
        $this->ensureNotificationColumns();

        // Mark all items of this station as 'served'
        $now = \Carbon\Carbon::now();
        DB::statement("
            UPDATE order_items oi
            SET status = 'served', served_at = ?
            FROM menu m
            WHERE oi.menu_id = m.id
              AND m.station_id = ?
              AND oi.order_id = ?
        ", [$now, $stationId, $orderId]);

        // Check if ALL station items across all stations are served
        $pendingStationItems = DB::selectOne("
            SELECT COUNT(*) as cnt FROM order_items oi
            INNER JOIN menu m ON m.id = oi.menu_id
            WHERE oi.order_id = ? AND m.station_id IS NOT NULL AND oi.status != 'served'
        ", [$orderId]);

        $dispatchCompleted = false;
        if ($pendingStationItems && $pendingStationItems->cnt == 0) {
            // Atomically claim the "completed" notification slot so concurrent
            // serveOrder calls (multi-station) or staff double-clicks do not
            // both dispatch the WhatsApp notification.
            $affected = DB::update("
                UPDATE orders
                   SET kitchen_status = 'served',
                       wa_completed_notified_at = ?
                 WHERE id = ?
                   AND wa_completed_notified_at IS NULL
            ", [$now, $orderId]);

            if ($affected > 0) {
                $dispatchCompleted = true;
            } else {
                // Already notified — still ensure kitchen_status is set
                DB::statement("UPDATE orders SET kitchen_status = 'served' WHERE id = ?", [$orderId]);
            }
        }

        DB::statement("SET search_path TO public");

        if ($dispatchCompleted) {
            $this->dispatchProgressNotification($outlet, (int) $orderId, 'completed');
        }

        return response()->json(['message' => 'Order marked as served']);
    }

    /**
     * Mark item as preparing
     */
    public function startItem(Request $request, $outletId, $stationId, $itemId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        DB::statement("SET search_path TO {$outlet->schema_name}, public");
        $this->ensureNotificationColumns();

        $now = \Carbon\Carbon::now();
        DB::statement("
            UPDATE order_items SET status = 'preparing', preparing_at = ? WHERE id = ?
        ", [$now, $itemId]);

        $orderId = DB::selectOne("SELECT order_id FROM order_items WHERE id = ?", [$itemId])?->order_id;
        $dispatchProcessing = false;

        if ($orderId) {
            DB::statement("
                UPDATE orders SET kitchen_status = 'preparing'
                WHERE id = ? AND kitchen_status = 'pending'
            ", [$orderId]);

            // Atomically claim the "processing" notification slot. Whether
            // kitchen or bar starts first, only the first staffer wins the
            // race and triggers the WhatsApp message.
            $affected = DB::update("
                UPDATE orders
                   SET wa_processing_notified_at = ?
                 WHERE id = ?
                   AND wa_processing_notified_at IS NULL
            ", [$now, $orderId]);
            $dispatchProcessing = $affected > 0;
        }

        DB::statement("SET search_path TO public");

        if ($dispatchProcessing && $orderId) {
            $this->dispatchProgressNotification($outlet, (int) $orderId, 'processing');
        }

        return response()->json(['message' => 'Item marked as preparing']);
    }

    /**
     * Heal-on-write: add notification idempotency columns for outlets
     * provisioned before the per-outlet template feature shipped.
     */
    protected function ensureNotificationColumns(): void
    {
        try {
            DB::statement("ALTER TABLE orders ADD COLUMN IF NOT EXISTS wa_processing_notified_at TIMESTAMP NULL");
            DB::statement("ALTER TABLE orders ADD COLUMN IF NOT EXISTS wa_ready_notified_at TIMESTAMP NULL");
            DB::statement("ALTER TABLE orders ADD COLUMN IF NOT EXISTS wa_completed_notified_at TIMESTAMP NULL");
        } catch (\Throwable $e) {
            // Older PostgreSQL without IF NOT EXISTS — best-effort, ignore.
        }
    }

    protected function dispatchProgressNotification(Outlet $outlet, int $orderId, string $event): void
    {
        // dispatchAfterResponse runs in the same PHP worker after the HTTP
        // response is flushed — does not require `queue:work` running, which
        // is the common cause of "no WhatsApp arrives" in production.
        $outletName = (string) ($outlet->nama ?? $outlet->name ?? '');
        try {
            SendOrderProgressWhatsApp::dispatchAfterResponse(
                $outlet->schema_name,
                $orderId,
                $outletName,
                $event,
                (int) $outlet->id
            );
            Log::info("[WAHA] Queued progress notification ({$event}) for order #{$orderId} in {$outlet->schema_name}");
        } catch (\Throwable $e) {
            Log::warning("[WAHA] Failed to dispatch progress job ({$event}) for order #{$orderId}: " . $e->getMessage());
        }
    }
}
