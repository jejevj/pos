<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Public takeaway-ordering endpoints — no auth required.
 *
 *   GET  /api/public/outlet/{outletSlug}/takeaway
 *   POST /api/public/outlet/{outletSlug}/takeaway/order
 *
 * Status / update / proof endpoints reuse the same /order/{code} routes used
 * by TableOrderController (source = 'public') — order code is uniform across
 * dine-in and takeaway.
 */
class TakeawayOrderController extends Controller
{
    use PublicOrderingHelpers;

    /**
     * Initial page payload — outlet info, menu list, categories, online payment methods.
     * No table token / no per-table state.
     */
    public function show(Request $request, string $outletSlug)
    {
        $outlet = $this->resolveOutletBySlug($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        try {
            $this->useSchema($outlet->schema_name);
            $this->healOnlineOrderColumns();

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
                'order_type'      => 'takeaway',
                'categories'      => $categories,
                'menu'            => $menu,
                'payment_methods' => $paymentMethods,
                'promos'          => $promos,
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

    public function store(Request $request, string $outletSlug)
    {
        $outlet = $this->resolveOutletBySlug($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_name'   => 'required|string|max:255',
            'customer_phone'  => 'required|string|max:50',
            'customer_email'  => 'required|email|max:255',
            'notes'           => 'nullable|string|max:500',
            'member_card'     => 'nullable|string|max:50',
            'payment_method_id' => 'required|integer',
            'payment_proof'   => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'promo_code'      => 'nullable|string|max:50',
            'items'           => 'required',
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

            // Price items server-side
            $payload = [];
            $subtotal = 0;
            foreach ($items as $it) {
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
                $qty = (int) $it['quantity'];
                $price = (float) $menu->harga_jual;
                $line = $price * $qty;
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

            // Server-side promo validation
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

            // Generate kode
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

            $proofPath = $this->storePaymentProof($request->file('payment_proof'), $outlet, $kode);

            $now = now();
            $orderRow = [
                'kode'             => $kode,
                'order_type'       => 'takeaway',
                'table_id'         => null,
                'table_number'     => null,
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

            foreach ($payload as $row) {
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
                'message' => 'Pesanan takeaway dibuat, menunggu persetujuan kasir',
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
}
