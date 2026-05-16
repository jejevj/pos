<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Public promo lookup/validation endpoints — no auth required.
 *
 * Used by the self-order (table & takeaway) checkout pages so customers can:
 *   - list promos flagged self-order available for the outlet
 *   - validate a promo code against a tentative subtotal
 *
 * Authoritative discount calc still happens server-side at order creation;
 * these endpoints exist only to drive the UI before submit.
 */
class PromoLookupController extends Controller
{
    use PublicOrderingHelpers;

    /**
     * GET /api/public/outlet/{outletSlug}/promos?subtotal=...
     */
    public function index(Request $request, string $outletSlug)
    {
        $outlet = $this->resolveOutletBySlug($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }
        try {
            $this->useSchema($outlet->schema_name);
            $this->healSelfOrderPromoColumn();
            $subtotal = $request->query('subtotal');
            $subtotal = $subtotal !== null && $subtotal !== '' ? (float) $subtotal : null;
            $promos = $this->listSelfOrderPromos($subtotal);
            $this->resetSchema();
            return response()->json(['promos' => $promos]);
        } catch (\Throwable $e) {
            $this->resetSchema();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/public/outlet/{outletSlug}/promo/validate
     * body: { kode: string, subtotal: number }
     */
    public function validateCode(Request $request, string $outletSlug)
    {
        $outlet = $this->resolveOutletBySlug($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }
        $v = Validator::make($request->all(), [
            'kode'     => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Data tidak valid', 'errors' => $v->errors()], 422);
        }
        try {
            $this->useSchema($outlet->schema_name);
            $this->healSelfOrderPromoColumn();
            $result = $this->applySelfOrderPromo($request->input('kode'), (float) $request->input('subtotal'));
            $this->resetSchema();
            if ($result['error']) {
                return response()->json(['valid' => false, 'message' => $result['error']], 200);
            }
            return response()->json([
                'valid'           => true,
                'discount_amount' => $result['discount'],
                'promo'           => $result['applied'],
            ]);
        } catch (\Throwable $e) {
            $this->resetSchema();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
