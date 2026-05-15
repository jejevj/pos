<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public order tracking — no authentication required.
 * Accessible via: GET /api/track/{outletId}/{orderCode}
 *
 * outletId can be either a numeric DB id or the 8-char hex hash
 * produced by frontend-app/src/utils/outletId.js (XOR-encoded).
 */
class OrderTrackingController extends Controller
{
    /** Must match SEED in frontend-app/src/utils/outletId.js */
    private const OUTLET_ID_SEED = 0x504F5300;

    public function show(Request $request, $outletId, $orderCode)
    {
        $numericId = $this->resolveOutletId($outletId);
        $outlet = $numericId ? Outlet::find($numericId) : null;

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
                    oi.menu_price,
                    oi.quantity,
                    oi.subtotal,
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

            // Receipt / transaction settings (per-outlet)
            $rs = DB::table('transaction_settings')->first();

            DB::statement("SET search_path TO public");

            // Cashier name (public schema)
            $cashierName = null;
            if (!empty($order->cashier_id)) {
                $cashier = DB::table('users')->where('id', $order->cashier_id)->first();
                $cashierName = $cashier->name ?? null;
            }

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
                    'menu_price'  => (float) ($item->menu_price ?? 0),
                    'quantity'    => (int) $item->quantity,
                    'subtotal'    => (float) ($item->subtotal ?? 0),
                    'notes'       => $item->notes,
                    'status'      => $item->status ?? 'pending',
                    'preparing_at'=> $item->preparing_at,
                    'ready_at'    => $item->ready_at,
                    'served_at'   => $item->served_at,
                ];
            }

            $taxEnabled            = $rs ? (bool) ($rs->tax_enabled ?? true) : true;
            $taxLabel              = $rs ? ($rs->tax_label ?? 'PPN') : 'PPN';
            $taxPercentage         = $rs ? (float) ($rs->tax_percentage ?? 0) : 0;
            $serviceChargeEnabled  = $rs ? (bool) ($rs->service_charge_enabled ?? false) : false;
            $serviceChargeLabel    = $rs ? ($rs->service_charge_label ?? 'Service Charge') : 'Service Charge';
            $serviceChargePct      = $rs ? (float) ($rs->service_charge_percentage ?? 0) : 0;

            $receiptSettings = [
                'receipt_header'        => $rs ? ($rs->receipt_header ?? '') : '',
                'receipt_footer'        => $rs ? ($rs->receipt_footer ?? '') : '',
                'receipt_show_qr'       => $rs ? (bool) ($rs->receipt_show_qr ?? true) : true,
                'receipt_wifi_enabled'  => $rs ? (bool) ($rs->receipt_wifi_enabled ?? false) : false,
                'receipt_wifi_ssid'     => $rs ? ($rs->receipt_wifi_ssid ?? '') : '',
                'receipt_wifi_password' => $rs ? ($rs->receipt_wifi_password ?? '') : '',
                'receipt_logo_enabled'  => $rs ? (bool) ($rs->receipt_logo_enabled ?? true) : true,
                'receipt_show_cashier'  => $rs ? (bool) ($rs->receipt_show_cashier ?? true) : true,
                'receipt_show_table'    => $rs ? (bool) ($rs->receipt_show_table ?? true) : true,
                'tax_enabled'           => $taxEnabled,
                'tax_label'             => $taxLabel,
                'tax_percentage'        => $taxPercentage,
                'service_charge_enabled'=> $serviceChargeEnabled,
                'service_charge_label'  => $serviceChargeLabel,
                'service_charge_percentage' => $serviceChargePct,
            ];

            return response()->json([
                'outlet' => [
                    'id'      => $outlet->id,
                    'name'    => $outlet->name,
                    'address' => $outlet->address,
                    'phone'   => $outlet->phone,
                    'logo'    => $outlet->logo,
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
                    'subtotal'                  => (float) ($order->subtotal ?? 0),
                    'discount_amount'           => (float) ($order->discount_amount ?? 0),
                    'tax_percentage'            => (float) ($order->tax_percentage ?? 0),
                    'tax_amount'                => (float) ($order->tax_amount ?? 0),
                    'service_charge_percentage' => (float) ($order->service_charge_percentage ?? 0),
                    'service_charge_amount'     => (float) ($order->service_charge_amount ?? 0),
                    'total_amount'              => (float) ($order->total_amount ?? 0),
                    'cashier_name'   => $cashierName,
                    'created_at'     => $order->created_at,
                    'paid_at'        => $order->paid_at,
                ],
                'stations'         => array_values($byStation),
                'timeline'         => $timeline,
                'receipt_settings' => $receiptSettings,
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Decode an outlet ID that may be:
     *   - a plain numeric DB id ("1"), or
     *   - an 8-char hex XOR-encoded hash ("504f5301") produced by the frontend.
     * Returns null if neither form yields a positive integer.
     */
    private function resolveOutletId($raw): ?int
    {
        if ($raw === null || $raw === '') return null;

        if (is_string($raw) && preg_match('/^[0-9a-fA-F]{8}$/', $raw)) {
            $n = (hexdec($raw) ^ self::OUTLET_ID_SEED) & 0xFFFFFFFF;
            return $n > 0 ? (int) $n : null;
        }

        if (is_numeric($raw)) {
            $n = (int) $raw;
            return $n > 0 ? $n : null;
        }

        return null;
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
