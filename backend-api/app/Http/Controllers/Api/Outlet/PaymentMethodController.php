<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentMethodController extends Controller
{
    use AuthorizesOutletAccess;


    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $this->ensureColumns();
            $query = PaymentMethod::query();
            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }
            $methods = $query->ordered()->get();
            DB::statement("SET search_path TO public");
            $methods->each(function ($m) {
                $m->qr_image_url = $this->buildQrImageUrl($m->qr_image_path ?? null);
            });
            return response()->json($methods);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $request->validate([
            'name'          => 'required|string|max:100',
            'code'          => 'required|string|max:50',
            'icon'          => 'nullable|string|max:255',
            'is_active'     => 'boolean',
            'display_order' => 'integer|min:0',
            'defers_stock'  => 'boolean',
            'is_online_orderable' => 'boolean',
        ]);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $this->ensureColumns();
            $method = PaymentMethod::create([
                'name'          => $request->name,
                'code'          => $request->code,
                'icon'          => $request->icon,
                'is_active'     => $request->boolean('is_active', true),
                'display_order' => $request->input('display_order', 99),
                'defers_stock'  => $request->boolean('defers_stock', false),
                'is_online_orderable' => $request->boolean('is_online_orderable', false),
            ]);
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Payment method created', 'data' => $method], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $request->validate([
            'name'          => 'string|max:100',
            'code'          => 'string|max:50',
            'icon'          => 'nullable|string|max:255',
            'is_active'     => 'boolean',
            'display_order' => 'integer|min:0',
            'defers_stock'  => 'boolean',
            'is_online_orderable' => 'boolean',
        ]);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $this->ensureColumns();
            $method = PaymentMethod::findOrFail($id);
            $method->update($request->only(['name', 'code', 'icon', 'is_active', 'display_order', 'defers_stock', 'is_online_orderable']));
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Payment method updated', 'data' => $method]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $method = PaymentMethod::findOrFail($id);
            // Prevent deleting if used in orders
            $used = DB::table('orders')->where('payment_method_id', $id)->exists();
            if ($used) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Cannot delete — payment method is used in orders'], 422);
            }
            $method->delete();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Payment method deleted']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ── QR Image (e.g. QRIS) upload / removal ────────────────────────────────

    public function uploadQr(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $validator = Validator::make($request->all(), [
            'qr_image' => 'required|file|mimes:jpg,jpeg,png,webp|max:3072',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
        }
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $this->ensureColumns();
            $method = PaymentMethod::findOrFail($id);

            $oldPath = $method->qr_image_path;
            $file = $request->file('qr_image');
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
            $safeExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'png';
            $filename = 'qr_' . $method->code . '_' . Str::random(8) . '.' . $safeExt;
            $dir = 'uploads/payment_qr/' . $outlet->slug;
            $path = $file->storeAs($dir, $filename, 'public');

            $method->update(['qr_image_path' => $path]);

            if ($oldPath && $oldPath !== $path) {
                try { Storage::disk('public')->delete($oldPath); } catch (\Throwable $e) { /* ignore */ }
            }

            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'QR image uploaded',
                'data' => [
                    'id'            => $method->id,
                    'qr_image_path' => $method->qr_image_path,
                    'qr_image_url'  => $this->buildQrImageUrl($method->qr_image_path),
                ],
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function deleteQr($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $this->ensureColumns();
            $method = PaymentMethod::findOrFail($id);
            $oldPath = $method->qr_image_path;
            $method->update(['qr_image_path' => null]);
            if ($oldPath) {
                try { Storage::disk('public')->delete($oldPath); } catch (\Throwable $e) { /* ignore */ }
            }
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'QR image removed']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function buildQrImageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        return url(Storage::url($path));
    }

    // ── Bon (deferred payment) list ──────────────────────────────────────────

    public function getBonList(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $query = DB::table('orders')
                ->join('payment_methods', 'orders.payment_method_id', '=', 'payment_methods.id')
                ->leftJoin('outlet_users', 'orders.cashier_id', '=', 'outlet_users.id')
                ->where('orders.status', 'bon')
                ->whereNull('orders.deleted_at')
                ->select(
                    'orders.*',
                    'payment_methods.name as payment_method_name',
                    'outlet_users.name as cashier_name'
                )
                ->orderBy('orders.created_at', 'desc');

            if ($request->has('date')) {
                $query->whereDate('orders.created_at', $request->date);
            }

            $bons = $query->get();
            DB::statement("SET search_path TO public");
            return response()->json($bons);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function ensureColumns(): void
    {
        $schema = DB::getSchemaBuilder();
        if (!$schema->hasColumn('payment_methods', 'defers_stock')) {
            DB::statement("ALTER TABLE payment_methods ADD COLUMN defers_stock BOOLEAN DEFAULT FALSE");
        }
        if (!$schema->hasColumn('payment_methods', 'is_online_orderable')) {
            DB::statement("ALTER TABLE payment_methods ADD COLUMN is_online_orderable BOOLEAN DEFAULT FALSE");
            // Default-enable QRIS for online ordering — safe assumption for typical
            // outlets that already use QRIS as their cashless instrument.
            DB::statement("UPDATE payment_methods SET is_online_orderable = TRUE WHERE code = 'qris'");
        }
        if (!$schema->hasColumn('payment_methods', 'qr_image_path')) {
            DB::statement("ALTER TABLE payment_methods ADD COLUMN qr_image_path VARCHAR(500) NULL");
        }
        if (!$schema->hasColumn('orders', 'payment_proof_path')) {
            DB::statement("ALTER TABLE orders ADD COLUMN payment_proof_path VARCHAR(500) NULL");
        }
        if (!$schema->hasColumn('orders', 'payment_proof_uploaded_at')) {
            DB::statement("ALTER TABLE orders ADD COLUMN payment_proof_uploaded_at TIMESTAMP NULL");
        }
        if (!$schema->hasColumn('orders', 'payment_status')) {
            DB::statement("ALTER TABLE orders ADD COLUMN payment_status VARCHAR(20) DEFAULT 'paid'");
        }
        if (!$schema->hasColumn('orders', 'settled_at')) {
            DB::statement("ALTER TABLE orders ADD COLUMN settled_at TIMESTAMP NULL");
        }
        if (!$schema->hasColumn('orders', 'settled_by')) {
            DB::statement("ALTER TABLE orders ADD COLUMN settled_by BIGINT NULL");
        }
        // Ensure Bon payment method exists
        $bonExists = DB::table('payment_methods')->where('code', 'bon')->exists();
        if (!$bonExists) {
            $maxOrder = DB::table('payment_methods')->max('display_order') ?? 6;
            DB::table('payment_methods')->insert([
                'name'          => 'Bon',
                'code'          => 'bon',
                'icon'          => 'pi pi-file-edit',
                'is_active'     => true,
                'display_order' => $maxOrder + 1,
                'defers_stock'  => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }
}
