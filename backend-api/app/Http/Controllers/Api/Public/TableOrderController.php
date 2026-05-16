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
 *   POST /api/public/outlet/{outletSlug}/order/{orderCode}/proof  (upload/replace payment proof)
 *
 * Outlet is resolved by outlets.slug; table by tables.qr_token (per-outlet schema).
 * Order code returned on create is the same kode used by the existing tracking page.
 */
class TableOrderController extends Controller
{
    use PublicOrderingHelpers;

    /**
     * Initial page payload — outlet info, table info, menu list, categories,
     * available online-orderable payment methods.
     */
    public function show(Request $request, string $outletSlug, string $token)
    {
        $outlet = $this->resolveOutletBySlug($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        try {
            $this->useSchema($outlet->schema_name);
            $this->healOnlineOrderColumns();

            $table = DB::table('tables')
                ->where('qr_token', $token)
                ->whereNull('deleted_at')
                ->first();

            if (!$table) {
                $this->resetSchema();
                return response()->json(['message' => 'Meja tidak ditemukan'], 404);
            }

            // Table is "orderable" only when active AND status = 'available'.
            // Token itself stays valid (static/reusable) — only the order form
            // is gated by current table status so a single QR can be scanned
            // again next visit without regeneration.
            $isOrderable = ((bool) $table->is_active) && ($table->status === 'available');

            if (!$isOrderable) {
                $this->resetSchema();
                return response()->json([
                    'outlet' => [
                        'id'      => $outlet->id,
                        'name'    => $outlet->name,
                        'slug'    => $outlet->slug,
                        'logo'    => $outlet->logo,
                        'address' => $outlet->address,
                    ],
                    'table' => [
                        'id'           => $table->id,
                        'table_number' => $table->table_number,
                        'area'         => $table->area,
                        'qr_token'     => $table->qr_token,
                        'status'       => $table->status,
                        'is_active'    => (bool) $table->is_active,
                    ],
                    'is_orderable' => false,
                    'unavailable_reason' => !$table->is_active
                        ? 'inactive'
                        : ($table->status ?: 'unavailable'),
                ], 200);
            }

            $categories = DB::table('kategori_menu')
                ->where('is_active', true)
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(['id', 'nama', 'urutan']);

            $menu = DB::table('menu')
                ->where('is_active', true)
                ->where('is_available', true)
                ->orderBy('nama')
                ->get([
                    'id', 'kode', 'nama', 'harga_jual', 'kategori_id',
                    'gambar_url', 'deskripsi', 'station_id',
                ]);

            $tx = DB::table('transaction_settings')->first();
            $member = DB::table('membership_settings')->first();
            $registrationOpen = $member ? (bool) ($member->registration_open ?? false) : false;
            $paymentMethods = $this->onlineOrderablePaymentMethods();
            $this->healSelfOrderPromoColumn();
            $promos = $this->listSelfOrderPromos();

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
                    'status'       => $table->status,
                    'is_active'    => (bool) $table->is_active,
                ],
                'is_orderable' => true,
                'categories' => $categories,
                'menu' => $menu,
                'payment_methods' => $paymentMethods,
                'promos'   => $promos,
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
     * Requires payment_method_id and a payment_proof file.
     */
    public function store(Request $request, string $outletSlug, string $token)
    {
        $outlet = $this->resolveOutletBySlug($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'customer_email' => 'required|email|max:255',
            'notes'          => 'nullable|string|max:500',
            'member_card'    => 'nullable|string|max:50',
            'payment_method_id' => 'required|integer',
            'payment_proof'  => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'promo_code'     => 'nullable|string|max:50',
            // Items come in as JSON string when multipart/form-data; the
            // frontend will JSON-encode before sending. Decoded below.
            'items'          => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
        }

        $items = $this->decodeItems($request->input('items'));
        $itemsValidator = Validator::make(['items' => $items], [
            'items'                => 'required|array|min:1',
            'items.*.menu_id'      => 'required|integer',
            'items.*.quantity'     => 'required|integer|min:1|max:50',
            'items.*.notes'        => 'nullable|string|max:255',
        ]);
        if ($itemsValidator->fails()) {
            return response()->json(['message' => 'Item pesanan tidak valid', 'errors' => $itemsValidator->errors()], 422);
        }

        try {
            $this->useSchema($outlet->schema_name);
            $this->healOnlineOrderColumns();
            DB::beginTransaction();

            $table = DB::table('tables')
                ->where('qr_token', $token)
                ->whereNull('deleted_at')
                ->first();
            if (!$table) {
                DB::rollBack();
                $this->resetSchema();
                return response()->json(['message' => 'Meja tidak ditemukan'], 404);
            }
            if (!$table->is_active || $table->status !== 'available') {
                DB::rollBack();
                $this->resetSchema();
                return response()->json([
                    'message' => 'Meja sedang tidak tersedia untuk pemesanan',
                ], 409);
            }

            if (!$this->assertOnlinePaymentMethod($request->payment_method_id)) {
                DB::rollBack();
                $this->resetSchema();
                return response()->json([
                    'message' => 'Metode pembayaran tidak tersedia untuk pemesanan online',
                ], 422);
            }

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

            [$itemsPayload, $subtotal] = $this->priceItems($items);

            // Server-side promo validation: never trust client discount.
            $this->healSelfOrderPromoColumn();
            $promoResult = $this->applySelfOrderPromo($request->input('promo_code'), (float) $subtotal);
            if ($promoResult['error']) {
                DB::rollBack();
                $this->resetSchema();
                return response()->json(['message' => $promoResult['error']], 422);
            }
            $discountAmount = $promoResult['discount'];
            $appliedPromos  = $promoResult['applied'] ? [$promoResult['applied']] : [];
            $primaryPromo   = $promoResult['promo'];

            $subtotalAfterDiscount = max(0, $subtotal - $discountAmount);

            $tx = DB::table('transaction_settings')->first();
            $taxPct = ($tx && $tx->tax_enabled) ? (float) $tx->tax_percentage : 0;
            $scPct  = ($tx && $tx->service_charge_enabled) ? (float) $tx->service_charge_percentage : 0;
            $taxAmount = round($subtotalAfterDiscount * $taxPct / 100, 2);
            $scAmount  = round($subtotalAfterDiscount * $scPct  / 100, 2);
            $total     = $subtotalAfterDiscount + $taxAmount + $scAmount;

            $kode = $this->generateOrderCode();

            // Store proof BEFORE inserting so we have a path to set on the row.
            $proofPath = $this->storePaymentProof($request->file('payment_proof'), $outlet, $kode);

            $now = now();
            $orderRow = [
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
                'payment_method_id' => $request->payment_method_id,
                'payment_proof_path' => $proofPath,
                'payment_proof_uploaded_at' => $now,
                'notes'            => $request->notes,
                'source'           => 'public',
                'approval_status'  => 'pending',
                'kitchen_status'   => 'pending',
                'cashier_id'       => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
            if ($primaryPromo) {
                $orderRow['promo_id']        = $primaryPromo->id;
                $orderRow['promo_code']      = $primaryPromo->kode;
                $orderRow['discount_type']   = $primaryPromo->tipe;
                $orderRow['discount_value']  = $primaryPromo->nilai;
                $orderRow['discount_amount'] = $discountAmount;
                $orderRow['applied_promos']  = json_encode($appliedPromos);
            }
            $orderId = DB::table('orders')->insertGetId($orderRow);

            foreach ($itemsPayload as $row) {
                $row['order_id']   = $orderId;
                $row['status']     = 'pending';
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                DB::table('order_items')->insert($row);
            }

            DB::commit();

            $order = DB::table('orders')->where('id', $orderId)->first();
            $itemsRows = DB::table('order_items')->where('order_id', $orderId)->get();

            $this->resetSchema();

            return response()->json([
                'message' => 'Pesanan dibuat, menunggu persetujuan kasir',
                'data'    => [
                    'order'  => $order,
                    'items'  => $itemsRows,
                    'payment_proof_url' => $this->publicProofUrl($proofPath),
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
        $outlet = $this->resolveOutletBySlug($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        try {
            $this->useSchema($outlet->schema_name);
            $this->healOnlineOrderColumns();

            $order = DB::table('orders')
                ->where('kode', $orderCode)
                ->where('source', 'public')
                ->first();
            if (!$order) {
                $this->resetSchema();
                return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
            }

            $items = DB::table('order_items')->where('order_id', $order->id)->get();

            // expose proof url (public-readable since we used the public disk)
            $proofUrl = $this->publicProofUrl($order->payment_proof_path ?? null);

            $this->resetSchema();

            return response()->json([
                'order' => $order,
                'items' => $items,
                'payment_proof_url' => $proofUrl,
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
        $outlet = $this->resolveOutletBySlug($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'items'                => 'required|array|min:1',
            'items.*.menu_id'      => 'required|integer',
            'items.*.quantity'     => 'required|integer|min:1|max:50',
            'items.*.notes'        => 'nullable|string|max:255',
            'notes'                => 'nullable|string|max:500',
            'promo_code'           => 'nullable|string|max:50',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
        }

        try {
            $this->useSchema($outlet->schema_name);
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

            // Re-validate/re-apply promo against new subtotal. If the request
            // explicitly carries promo_code we use that; otherwise we keep the
            // existing promo on the order if it is still valid.
            $this->healSelfOrderPromoColumn();
            $promoCode = $request->has('promo_code')
                ? $request->input('promo_code')
                : ($order->promo_code ?? null);
            $promoResult = $this->applySelfOrderPromo($promoCode, (float) $subtotal);
            // For update, treat ineligible promo silently (drop it) rather
            // than 422 — items might have changed making min-purchase fail.
            $discountAmount = $promoResult['discount'];
            $appliedPromos  = $promoResult['applied'] ? [$promoResult['applied']] : [];
            $primaryPromo   = $promoResult['promo'];

            $subtotalAfterDiscount = max(0, $subtotal - $discountAmount);
            $taxPct = (float) ($order->tax_percentage ?? 0);
            $scPct  = (float) ($order->service_charge_percentage ?? 0);
            $taxAmount = round($subtotalAfterDiscount * $taxPct / 100, 2);
            $scAmount  = round($subtotalAfterDiscount * $scPct  / 100, 2);
            $total     = $subtotalAfterDiscount + $taxAmount + $scAmount;

            DB::table('orders')->where('id', $order->id)->update([
                'subtotal'              => $subtotal,
                'tax_amount'            => $taxAmount,
                'service_charge_amount' => $scAmount,
                'total_amount'          => $total,
                'notes'                 => $request->notes ?? $order->notes,
                'promo_id'              => $primaryPromo->id ?? null,
                'promo_code'            => $primaryPromo->kode ?? null,
                'discount_type'         => $primaryPromo->tipe ?? null,
                'discount_value'        => $primaryPromo->nilai ?? null,
                'discount_amount'       => $discountAmount,
                'applied_promos'        => $primaryPromo ? json_encode($appliedPromos) : null,
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

    /**
     * Replace payment proof on a still-pending public order.
     */
    public function uploadProof(Request $request, string $outletSlug, string $orderCode)
    {
        $outlet = $this->resolveOutletBySlug($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }
        $validator = Validator::make($request->all(), [
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
        }
        try {
            $this->useSchema($outlet->schema_name);
            $this->healOnlineOrderColumns();

            $order = DB::table('orders')
                ->where('kode', $orderCode)
                ->where('source', 'public')
                ->first();
            if (!$order) {
                $this->resetSchema();
                return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
            }
            if ($order->approval_status !== 'pending') {
                $this->resetSchema();
                return response()->json(['message' => 'Pesanan sudah diproses, bukti tidak dapat diganti'], 409);
            }
            $proofPath = $this->storePaymentProof($request->file('payment_proof'), $outlet, $orderCode);
            DB::table('orders')->where('id', $order->id)->update([
                'payment_proof_path' => $proofPath,
                'payment_proof_uploaded_at' => now(),
                'updated_at' => now(),
            ]);
            $this->resetSchema();
            return response()->json([
                'message' => 'Bukti pembayaran berhasil diunggah',
                'data' => [
                    'payment_proof_url' => $this->publicProofUrl($proofPath),
                ],
            ]);
        } catch (\Exception $e) {
            $this->resetSchema();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private function decodeItems($items): array
    {
        if (is_array($items)) {
            return $items;
        }
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function priceItems(array $items): array
    {
        $payload = [];
        $subtotal = 0;
        foreach ($items as $it) {
            $menu = DB::table('menu')
                ->where('id', $it['menu_id'])
                ->where('is_active', true)
                ->where('is_available', true)
                ->first();
            if (!$menu) {
                throw new \RuntimeException("Menu tidak tersedia (id {$it['menu_id']})");
            }
            $qty   = (int) $it['quantity'];
            $price = (float) $menu->harga_jual;
            $line  = $price * $qty;
            $subtotal += $line;
            $payload[] = [
                'menu_id'    => $menu->id,
                'menu_name'  => $menu->nama,
                'menu_price' => $price,
                'quantity'   => $qty,
                'subtotal'   => $line,
                'notes'      => $it['notes'] ?? null,
            ];
        }
        return [$payload, $subtotal];
    }

    private function generateOrderCode(): string
    {
        $datePrefix = 'ORD' . date('Ymd');
        $last = DB::table('orders')
            ->where('kode', 'like', $datePrefix . '%')
            ->orderBy('id', 'desc')
            ->first();
        $seq = 1;
        if ($last && preg_match('/(\d{4})$/', $last->kode, $m)) {
            $seq = ((int) $m[1]) + 1;
        }
        return $datePrefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
