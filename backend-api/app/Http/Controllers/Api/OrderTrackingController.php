<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public order tracking — no authentication required.
 * Accessible via: GET /api/track/{outletId}/{orderCode}
 */
class OrderTrackingController extends Controller
{
    public function show(Request $request, $outletId, $orderCode)
    {
        $outlet = Outlet::find($outletId);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet not found'], 404);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $order = DB::table('orders')
                ->where('kode', $orderCode)
                ->whereNull('deleted_at')
                ->first();

            if (!$order) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Load items with station info
            $items = DB::select("
                SELECT
                    oi.id,
                    oi.menu_name,
                    oi.quantity,
                    oi.notes,
                    oi.status,
                    oi.preparing_at,
                    oi.ready_at,
                    oi.served_at,
                    m.station_id,
                    s.nama  AS station_name,
                    s.warna AS station_color,
                    s.icon  AS station_icon
                FROM order_items oi
                LEFT JOIN menu m ON m.id = oi.menu_id
                LEFT JOIN stations s ON s.id = m.station_id
                WHERE oi.order_id = ?
                ORDER BY oi.id ASC
            ", [$order->id]);

            // Table info
            $table = null;
            if ($order->table_id) {
                $table = DB::table('tables')->find($order->table_id);
            }

            DB::statement("SET search_path TO public");

            // Build status timeline
            $timeline = $this->buildTimeline($order, $items);

            // Group items by station
            $byStation = [];
            foreach ($items as $item) {
                $key = $item->station_id ?? 'no_station';
                if (!isset($byStation[$key])) {
                    $byStation[$key] = [
                        'station_id'    => $item->station_id,
                        'station_name'  => $item->station_name ?? 'Tanpa Stasiun',
                        'station_color' => $item->station_color ?? '#6b7280',
                        'station_icon'  => $item->station_icon ?? 'pi pi-box',
                        'items'         => [],
                    ];
                }
                $byStation[$key]['items'][] = [
                    'id'          => $item->id,
                    'menu_name'   => $item->menu_name,
                    'quantity'    => $item->quantity,
                    'notes'       => $item->notes,
                    'status'      => $item->status ?? 'pending',
                    'preparing_at'=> $item->preparing_at,
                    'ready_at'    => $item->ready_at,
                    'served_at'   => $item->served_at,
                ];
            }

            return response()->json([
                'outlet' => [
                    'id'   => $outlet->id,
                    'name' => $outlet->name,
                ],
                'order' => [
                    'id'             => $order->id,
                    'kode'           => $order->kode,
                    'order_type'     => $order->order_type,
                    'table_number'   => $order->table_number,
                    'customer_name'  => $order->customer_name,
                    'status'         => $order->status,
                    'kitchen_status' => $order->kitchen_status ?? 'pending',
                    'notes'          => $order->notes,
                    'created_at'     => $order->created_at,
                    'paid_at'        => $order->paid_at,
                ],
                'stations' => array_values($byStation),
                'timeline' => $timeline,
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function buildTimeline($order, array $items): array
    {
        $steps = [
            [
                'key'    => 'ordered',
                'label'  => 'Pesanan Diterima',
                'icon'   => 'pi pi-check-circle',
                'color'  => '#13DEB9',
                'done'   => true,
                'time'   => $order->created_at,
            ],
            [
                'key'    => 'preparing',
                'label'  => 'Sedang Diproses',
                'icon'   => 'pi pi-cog',
                'color'  => '#FFAE1F',
                'done'   => in_array($order->kitchen_status ?? '', ['preparing', 'ready', 'served']),
                'time'   => collect($items)->filter(fn($i) => $i->preparing_at)->min('preparing_at'),
            ],
            [
                'key'    => 'ready',
                'label'  => 'Siap Disajikan',
                'icon'   => 'pi pi-bell',
                'color'  => '#5D87FF',
                'done'   => in_array($order->kitchen_status ?? '', ['ready', 'served']),
                'time'   => collect($items)->filter(fn($i) => $i->ready_at)->max('ready_at'),
            ],
            [
                'key'    => 'served',
                'label'  => 'Sudah Disajikan',
                'icon'   => 'pi pi-star',
                'color'  => '#FA896B',
                'done'   => ($order->kitchen_status ?? '') === 'served',
                'time'   => collect($items)->filter(fn($i) => $i->served_at)->max('served_at'),
            ],
        ];

        return $steps;
    }
}
