<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Public table-ordering endpoints — no auth required.
 *
 *   GET  /api/public/outlet/{outletSlug}/table/{token}
 *   POST /api/public/outlet/{outletSlug}/table/{token}/order
 *   GET  /api/public/outlet/{outletSlug}/order/{orderCode}
 *   PUT  /api/public/outlet/{outletSlug}/order/{orderCode}        (only while approval_status = 'pending')
 *
 * Outlet is resolved by outlets.slug; table by tables.qr_token (per-outlet schema).
 * Order code returned on create is the same kode used by the existing tracking page.
 */
class TableOrderController extends Controller
{
    private function resolveOutlet(string $slug): ?Outlet
    {
        return Outlet::where('slug', $slug)->first();
    }

    private function setSchema(string $schema): void
    {
        DB::statement("SET search_path TO {$schema}, public");
    }

    private function resetSchema(): void
    {
        DB::statement("SET search_path TO public");
    }

    /**
     * Initial page payload — outlet info, table info, menu list, categories.
     */
    public function show(Request $request, string $outletSlug, string $token)
    {
        $outlet = $this->resolveOutlet($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        try {
            $this->setSchema($outlet->schema_name);

            $table = DB::table('tables')
                ->where('qr_token', $token)
                ->where('is_active', true)
                ->first();

            if (!$table) {
                $this->resetSchema();
                return response()->json(['message' => 'Meja tidak ditemukan atau tidak aktif'], 404);
            }

            // Categories
            $categories = DB::table('kategori_menu')
                ->where('is_active', true)
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(['id', 'nama', 'urutan']);

            // Menu items (available only)
            $menu = DB::table('menu')
                ->where('is_active', true)
                ->where('is_available', true)
                ->orderBy('nama')
                ->get([
                    'id', 'kode', 'nama', 'harga_jual', 'kategori_id',
                    'gambar_url', 'deskripsi', 'station_id',
                ]);

            // Transaction settings for tax/service display
            $tx = DB::table('transaction_settings')->first();

            // Public member registration availability (optional login)
            $member = DB::table('membership_settings')->first();
            $registrationOpen = $member ? (bool) ($member->registration_open ?? false) : false;

            $this->resetSchema();

            return response()->json([
                'outlet' => [
                    'id'   => $outlet->id,
                    'name' => $outlet->name,
                    'slug' => $outlet->slug,
                    'logo' => $outlet->logo,
                    'address' => $outlet->address,
                ],
                'table' => [
                    'id'           => $table->id,
                    'table_number' => $table->table_number,
                    'area'         => $table->area,
                    'qr_token'     => $table->qr_token,
                ],
                'categories' => $categories,
                'menu' => $menu,
                'settings' => [
                    'tax_enabled'              => $tx ? (bool) $tx->tax_enabled : false,
                    'tax_percentage'           => $tx ? (float) $tx->tax_percentage : 0,
                    'service_charge_enabled'   => $tx ? (bool) $tx->service_charge_enabled : false,
                    'service_charge_percentage' => $tx ? (float) $tx->service_charge_percentage : 0,
                    'membership_open'          => $registrationOpen,
                ],
            ]);
        } catch (\Exception $e) {
            $this->resetSchema();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new public order pending cashier approval.
     */
    public function store(Request $request, string $outletSlug, string $token)
    {
        $outlet = $this->resolveOutlet($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_name'  => 'nullable|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'customer_email' => 'required|email|max:255',
            'notes'          => 'nullable|string|max:500',
            'member_card'    => 'nullable|string|max:50',
            'items'                => 'required|array|min:1',
            'items.*.menu_id'      => 'required|integer',
            'items.*.quantity'     => 'required|integer|min:1|max:50',
            'items.*.notes'        => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
        }

        try {
            $this->setSchema($outlet->schema_name);
            DB::beginTransaction();

            $table = DB::table('tables')
                ->where('qr_token', $token)
                ->where('is_active', true)
                ->first();
            if (!$table) {
                DB::rollBack();
                $this->resetSchema();
                return response()->json(['message' => 'Meja tidak ditemukan'], 404);
            }

            // Optional member lookup (if customer logged-in via member_card)
            $memberId = null;
            if (!empty($request->member_card)) {
                $member = DB::table('members')
                    ->where('card_number', $request->member_card)
                    ->where('is_active', true)
                    ->first();
                if (!$member) {
                    DB::rollBack();
                    $this->resetSchema();
                    return response()->json([
                        'message' => 'Member tidak terdaftar di outlet ini. Silakan daftar terlebih dahulu atau pesan sebagai tamu.',
                    ], 403);
                }
                $memberId = $member->id;
            }

            // Validate and price items from server-side menu (never trust client prices)
            $itemsPayload = [];
            $subtotal = 0;
            foreach ($request->items as $it) {
                $menu = DB::table('menu')
                    ->where('id', $it['menu_id'])
                    ->where('is_active', true)
                    ->where('is_available', true)
                    ->first();
                if (!$menu) {
                    DB::rollBack();
                    $this->resetSchema();
                    return response()->json(['message' => "Menu tidak tersedia (id {$it['menu_id']})"], 422);
                }
                $qty   = (int) $it['quantity'];
                $price = (float) $menu->harga_jual;
                $line  = $price * $qty;
                $subtotal += $line;
                $itemsPayload[] = [
                    'menu_id'    => $menu->id,
                    'menu_name'  => $menu->nama,
                    'menu_price' => $price,
                    'quantity'   => $qty,
                    'subtotal'   => $line,
                    'notes'      => $it['notes'] ?? null,
                ];
            }

            // Tax & service charge from settings
            $tx = DB::table('transaction_settings')->first();
            $taxPct = ($tx && $tx->tax_enabled) ? (float) $tx->tax_percentage : 0;
            $scPct  = ($tx && $tx->service_charge_enabled) ? (float) $tx->service_charge_percentage : 0;

            $taxAmount = round($subtotal * $taxPct / 100, 2);
            $scAmount  = round($subtotal * $scPct  / 100, 2);
            $total     = $subtotal + $taxAmount + $scAmount;

            // Generate kode (ORD-YYYYMMDD-####)
            $datePrefix = 'ORD' . date('Ymd');
            $last = DB::table('orders')
                ->where('kode', 'like', $datePrefix . '%')
                ->orderBy('id', 'desc')
                ->first();
            $seq = 1;
            if ($last && preg_match('/(\d{4})$/', $last->kode, $m)) {
                $seq = ((int) $m[1]) + 1;
            }
            $kode = $datePrefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $now = now();
            $orderId = DB::table('orders')->insertGetId([
                'kode'             => $kode,
                'order_type'       => 'dine_in',
                'table_id'         => $table->id,
                'table_number'     => $table->table_number,
                'customer_name'    => $request->customer_name,
                'customer_phone'   => $request->customer_phone,
                'customer_email'   => $request->customer_email,
                'member_id'        => $memberId,
                'status'           => 'draft',
                'subtotal'         => $subtotal,
                'tax_percentage'   => $taxPct,
                'tax_amount'       => $taxAmount,
                'service_charge_percentage' => $scPct,
                'service_charge_amount'     => $scAmount,
                'total_amount'     => $total,
                'notes'            => $request->notes,
                'source'           => 'public',
                'approval_status'  => 'pending',
                'kitchen_status'   => 'pending',
                'cashier_id'       => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            foreach ($itemsPayload as $row) {
                $row['order_id']   = $orderId;
                $row['status']     = 'pending';
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                DB::table('order_items')->insert($row);
            }

            DB::commit();

            $order = DB::table('orders')->where('id', $orderId)->first();
            $items = DB::table('order_items')->where('order_id', $orderId)->get();

            $this->resetSchema();

            return response()->json([
                'message' => 'Pesanan dibuat, menunggu persetujuan kasir',
                'data'    => [
                    'order'  => $order,
                    'items'  => $items,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->resetSchema();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch status of a public order — used by the waiting screen on the
     * customer device to poll approval.
     */
    public function status(Request $request, string $outletSlug, string $orderCode)
    {
        $outlet = $this->resolveOutlet($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        try {
            $this->setSchema($outlet->schema_name);

            $order = DB::table('orders')
                ->where('kode', $orderCode)
                ->where('source', 'public')
                ->first();
            if (!$order) {
                $this->resetSchema();
                return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
            }

            $items = DB::table('order_items')->where('order_id', $order->id)->get();

            $this->resetSchema();

            return response()->json([
                'order' => $order,
                'items' => $items,
            ]);
        } catch (\Exception $e) {
            $this->resetSchema();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Edit a still-pending public order (replace items).
     * Once approval_status is approved/rejected the order is locked.
     */
    public function update(Request $request, string $outletSlug, string $orderCode)
    {
        $outlet = $this->resolveOutlet($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'items'                => 'required|array|min:1',
            'items.*.menu_id'      => 'required|integer',
            'items.*.quantity'     => 'required|integer|min:1|max:50',
            'items.*.notes'        => 'nullable|string|max:255',
            'notes'                => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
        }

        try {
            $this->setSchema($outlet->schema_name);
            DB::beginTransaction();

            $order = DB::table('orders')
                ->where('kode', $orderCode)
                ->where('source', 'public')
                ->first();
            if (!$order) {
                DB::rollBack();
                $this->resetSchema();
                return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
            }
            if ($order->approval_status !== 'pending') {
                DB::rollBack();
                $this->resetSchema();
                return response()->json(['message' => 'Pesanan sudah diproses, tidak bisa diubah'], 409);
            }

            DB::table('order_items')->where('order_id', $order->id)->delete();

            $subtotal = 0;
            $now = now();
            foreach ($request->items as $it) {
                $menu = DB::table('menu')
                    ->where('id', $it['menu_id'])
                    ->where('is_active', true)
                    ->where('is_available', true)
                    ->first();
                if (!$menu) {
                    DB::rollBack();
                    $this->resetSchema();
                    return response()->json(['message' => "Menu tidak tersedia (id {$it['menu_id']})"], 422);
                }
                $qty   = (int) $it['quantity'];
                $price = (float) $menu->harga_jual;
                $line  = $price * $qty;
                $subtotal += $line;
                DB::table('order_items')->insert([
                    'order_id'   => $order->id,
                    'menu_id'    => $menu->id,
                    'menu_name'  => $menu->nama,
                    'menu_price' => $price,
                    'quantity'   => $qty,
                    'subtotal'   => $line,
                    'notes'      => $it['notes'] ?? null,
                    'status'     => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $taxPct = (float) ($order->tax_percentage ?? 0);
            $scPct  = (float) ($order->service_charge_percentage ?? 0);
            $taxAmount = round($subtotal * $taxPct / 100, 2);
            $scAmount  = round($subtotal * $scPct  / 100, 2);
            $total     = $subtotal + $taxAmount + $scAmount;

            DB::table('orders')->where('id', $order->id)->update([
                'subtotal'              => $subtotal,
                'tax_amount'            => $taxAmount,
                'service_charge_amount' => $scAmount,
                'total_amount'          => $total,
                'notes'                 => $request->notes ?? $order->notes,
                'updated_at'            => $now,
            ]);

            DB::commit();

            $orderRow = DB::table('orders')->where('id', $order->id)->first();
            $items    = DB::table('order_items')->where('order_id', $order->id)->get();

            $this->resetSchema();

            return response()->json([
                'message' => 'Pesanan diperbarui',
                'data'    => ['order' => $orderRow, 'items' => $items],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->resetSchema();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
