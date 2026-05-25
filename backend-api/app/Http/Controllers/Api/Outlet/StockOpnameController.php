<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\BahanBaku;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockOpnameController extends Controller
{
    use AuthorizesOutletAccess;

    /**
     * Resolve outlet_user_id dari user yang sedang login (cari di outlet_users by email).
     * Harus dipanggil SETELAH search_path di-set ke schema outlet.
     */
    private function resolveOutletUserId(): ?int
    {
        $authUser = Auth::user();
        if (!$authUser) return null;
        $row = DB::table('outlet_users')
            ->whereRaw('LOWER(email) = ?', [strtolower($authUser->email)])
            ->whereNull('deleted_at')
            ->first();
        return $row ? (int) $row->id : null;
    }

    /**
     * Cek apakah user yang login punya permission manage_stock_opname di outlet ini.
     * Owner outlet (outlet_user.role owner) juga dianggap punya akses.
     */
    private function canManageOpname(string $schemaName, int $outletId): bool
    {
        $authUser = Auth::user();
        if (!$authUser) return false;

        // Superadmin selalu bisa
        if ($authUser->hasRole('superadmin')) return true;

        // Cek permission manage_stock_opname di schema outlet
        $outletUser = DB::table('outlet_users')
            ->whereRaw('LOWER(email) = ?', [strtolower($authUser->email)])
            ->whereNull('deleted_at')
            ->first();

        if (!$outletUser) return false;

        // Cek apakah owner
        $isOwner = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $outletUser->id)
            ->where('roles.name', 'owner')
            ->exists();

        if ($isOwner) return true;

        // Cek permission
        $hasPermission = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->join('role_permissions', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('user_roles.user_id', $outletUser->id)
            ->where('permissions.name', 'manage_stock_opname')
            ->exists();

        return $hasPermission;
    }


    /**
     * PIC options: users available in this outlet (active outlet_users plus
     * the outlet owner from main users table). Used by the stock opname form
     * to render a dropdown of valid PICs scoped to the current outlet.
     */
    public function picOptions($outletId)
    {
        $outlet = $this->authorizeOutlet($outletId, ['setSchema' => false]);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $outletUsers = DB::table('outlet_users')
                ->where('outlet_id', $outlet->id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get()
                ->map(fn ($u) => [
                    'id'     => (int) $u->id,
                    'name'   => $u->name,
                    'email'  => $u->email,
                    'source' => 'outlet_user',
                    'label'  => $u->name . ' (' . $u->email . ')',
                ]);

            DB::statement("SET search_path TO public");

            $options = $outletUsers->values()->all();

            $owner = $outlet->user_id ? DB::table('users')
                ->where('id', $outlet->user_id)
                ->select('id', 'name', 'email')
                ->first() : null;

            if ($owner) {
                $hasOwnerEmail = $outletUsers->contains(fn ($u) => strcasecmp($u['email'] ?? '', $owner->email) === 0);
                if (!$hasOwnerEmail) {
                    array_unshift($options, [
                        'id'     => (int) $owner->id,
                        'name'   => $owner->name,
                        'email'  => $owner->email,
                        'source' => 'owner',
                        'label'  => $owner->name . ' (Owner)',
                    ]);
                }
            }

            return response()->json(['data' => $options]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all stock opname records
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $isManager = $this->canManageOpname($outlet->schema_name, $outlet->id);
            $currentUserId = $this->resolveOutletUserId();

            $query = StockOpname::query();

            // Petugas biasa hanya lihat jadwal yang ditugaskan ke mereka
            if (!$isManager && $currentUserId) {
                $query->where('pic_user_id', $currentUserId);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $stockOpnames = $query->orderBy('created_at', 'desc')->get();

            // Tambah flag untuk frontend
            $stockOpnames->each(function ($so) use ($isManager, $currentUserId) {
                $so->can_manage = $isManager;
                $so->is_assigned_pic = $currentUserId && (int)$so->pic_user_id === $currentUserId;
            });

            DB::statement("SET search_path TO public");

            return response()->json($stockOpnames);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create new stock opname schedule.
     *
     * Location-aware:
     *  - If `location_ids` (array) is provided, opname detail rows are generated
     *    per (active material × selected location) pulling system_stock from
     *    bahan_baku_locations.current_stock (defaults to 0 when absent).
     *  - If empty/omitted, falls back to legacy global rows (stock_location_id
     *    NULL, system_stock = bahan_baku.current_stock).
     */
    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'pic_user_id' => 'required|integer|min:1',
            'pic_source' => 'nullable|in:outlet_user,owner',
            'pic_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            // Resolve PIC: must be a user that belongs to this outlet (outlet_user
            // in this schema, OR the outlet owner from main users table).
            $picUserId = (int) $request->input('pic_user_id');
            $picSource = $request->input('pic_source');
            $picName = null;

            if ($picSource === 'owner' || (!$picSource && $outlet->user_id === $picUserId)) {
                if ((int) $outlet->user_id !== $picUserId) {
                    return response()->json([
                        'message' => 'PIC tidak valid untuk outlet ini',
                    ], 422);
                }
                $owner = DB::table('users')->where('id', $picUserId)->first();
                if (!$owner) {
                    return response()->json(['message' => 'PIC tidak ditemukan'], 422);
                }
                $picName = $owner->name;
            } else {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                $picRow = DB::table('outlet_users')
                    ->where('id', $picUserId)
                    ->where('outlet_id', $outlet->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();
                DB::statement("SET search_path TO public");
                if (!$picRow) {
                    return response()->json([
                        'message' => 'PIC tidak terdaftar sebagai user aktif di outlet ini',
                    ], 422);
                }
                $picName = $picRow->name;
            }

            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            // Validate location_ids belong to this outlet schema (prevents cross-outlet ids)
            $requestedLocationIds = collect($request->input('location_ids', []))
                ->filter(fn ($id) => (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $validLocationIds = [];
            if (!empty($requestedLocationIds)) {
                $validLocationIds = DB::table('locations')
                    ->whereIn('id', $requestedLocationIds)
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();

                if (count($validLocationIds) !== count($requestedLocationIds)) {
                    DB::statement("SET search_path TO public");
                    return response()->json([
                        'message' => 'Beberapa location_id tidak valid untuk outlet ini',
                    ], 422);
                }
            }

            $stockOpname = StockOpname::create([
                'kode' => StockOpname::generateKode(),
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'status' => 'draft',
                'pic_name' => $picName,
                'pic_user_id' => $picUserId,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
            ]);

            $materials = BahanBaku::where('is_active', true)->get();

            if (!empty($validLocationIds)) {
                // Per-location rows: one detail per (material × location)
                $locStocks = DB::table('bahan_baku_locations')
                    ->whereIn('location_id', $validLocationIds)
                    ->get()
                    ->keyBy(fn ($r) => $r->bahan_baku_id . ':' . $r->location_id);

                foreach ($materials as $material) {
                    foreach ($validLocationIds as $locationId) {
                        $key = $material->id . ':' . $locationId;
                        $systemStock = isset($locStocks[$key]) ? (float) $locStocks[$key]->current_stock : 0;

                        StockOpnameDetail::create([
                            'stock_opname_id' => $stockOpname->id,
                            'bahan_baku_id' => $material->id,
                            'stock_location_id' => $locationId,
                            'system_stock' => $systemStock,
                        ]);
                    }
                }
            } else {
                // Legacy global rows
                foreach ($materials as $material) {
                    StockOpnameDetail::create([
                        'stock_opname_id' => $stockOpname->id,
                        'bahan_baku_id' => $material->id,
                        'stock_location_id' => null,
                        'system_stock' => $material->current_stock,
                    ]);
                }
            }

            $stockOpname->load('details');
            $stockOpname->total_items = $stockOpname->details->count();
            $stockOpname->save();

            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Stock opname created successfully',
                'data' => $stockOpname,
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get stock opname detail (with location info on each line)
     */
    public function show($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $stockOpname = StockOpname::with(['details.bahanBaku.satuan'])->findOrFail($id);

            // Hydrate location info into each detail (raw join since location is in same schema)
            $locationIds = $stockOpname->details
                ->pluck('stock_location_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $locationMap = [];
            if (!empty($locationIds)) {
                $locationMap = DB::table('locations')
                    ->whereIn('id', $locationIds)
                    ->get()
                    ->keyBy('id');
            }

            foreach ($stockOpname->details as $detail) {
                $loc = $detail->stock_location_id && isset($locationMap[$detail->stock_location_id])
                    ? $locationMap[$detail->stock_location_id]
                    : null;
                $detail->stock_location = $loc ? [
                    'id' => (int) $loc->id,
                    'name' => $loc->name,
                    'type' => $loc->type,
                ] : null;
            }

            $stockOpname->can_submit = $stockOpname->canSubmit();

            // Flag akses untuk frontend
            $isManager     = $this->canManageOpname($outlet->schema_name, $outlet->id);
            $currentUserId = $this->resolveOutletUserId();
            $stockOpname->can_manage      = $isManager;
            $stockOpname->is_assigned_pic = $currentUserId && (int)$stockOpname->pic_user_id === $currentUserId;

            DB::statement("SET search_path TO public");

            return response()->json($stockOpname);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update stock opname (fill physical stock) — hanya PIC yang ditunjuk atau manager
     */
    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            'details' => 'required|array',
            'details.*.id' => 'required|integer',
            'details.*.physical_stock' => 'nullable|numeric|min:0',
            'details.*.notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            DB::beginTransaction();

            $stockOpname = StockOpname::with(['details.bahanBaku'])->findOrFail($id);

            // Guard: hanya PIC atau manager yang boleh update
            $isManager     = $this->canManageOpname($outlet->schema_name, $outlet->id);
            $currentUserId = $this->resolveOutletUserId();
            $isPic         = $currentUserId && (int)$stockOpname->pic_user_id === $currentUserId;
            if (!$isManager && !$isPic) {
                DB::rollBack();
                DB::statement('SET search_path TO public');
                return response()->json(['message' => 'Anda bukan PIC yang ditugaskan untuk opname ini'], 403);
            }

            if (!$stockOpname->is_editable) {
                DB::rollBack();
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Stock opname cannot be edited in current status'], 400);
            }

            foreach ($request->details as $detailData) {
                $detail = $stockOpname->details->firstWhere('id', $detailData['id']);

                if ($detail) {
                    $detail->physical_stock = isset($detailData['physical_stock']) && $detailData['physical_stock'] !== ''
                        ? (float) $detailData['physical_stock']
                        : null;
                    $detail->notes = $detailData['notes'] ?? null;

                    if ($detail->physical_stock !== null && is_numeric($detail->physical_stock)) {
                        $detail->difference = $detail->physical_stock - $detail->system_stock;

                        if ($detail->bahanBaku) {
                            $pricePerUnit = $detail->bahanBaku->harga_per_satuan_dasar ?? $detail->bahanBaku->harga_beli ?? 0;
                            $detail->difference_value = $detail->difference * $pricePerUnit;
                        } else {
                            $detail->difference_value = 0;
                        }
                    } else {
                        $detail->difference = null;
                        $detail->difference_value = null;
                    }

                    $detail->save();
                }
            }

            if ($stockOpname->status === 'draft') {
                $stockOpname->status = 'in_progress';
                $stockOpname->save();
            }

            $stockOpname->calculateTotalDifferenceValue();

            DB::commit();

            $stockOpname->can_submit = $stockOpname->canSubmit();

            $stockOpname->load(['details.bahanBaku.satuan']);

            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Stock opname updated successfully',
                'data' => $stockOpname,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Submit for review
     */
    public function submit($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $stockOpname = StockOpname::with('details')->findOrFail($id);

            // Guard: hanya PIC atau manager yang boleh submit
            $isManager     = $this->canManageOpname($outlet->schema_name, $outlet->id);
            $currentUserId = $this->resolveOutletUserId();
            $isPic         = $currentUserId && (int)$stockOpname->pic_user_id === $currentUserId;
            if (!$isManager && !$isPic) {
                DB::statement('SET search_path TO public');
                return response()->json(['message' => 'Anda bukan PIC yang ditugaskan untuk opname ini'], 403);
            }

            if (!$stockOpname->canSubmit()) {
                return response()->json(['message' => 'Stock opname cannot be submitted'], 400);
            }

            $stockOpname->status = 'submitted';
            $stockOpname->save();

            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Stock opname submitted for review',
                'data' => $stockOpname,
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve stock opname — apply adjustments.
     *
     * Behavior:
     *  - Detail with stock_location_id NULL  → legacy global adjustment via
     *    BahanBaku::adjustStock (sets current_stock to physical_stock).
     *  - Detail with stock_location_id set   → upsert bahan_baku_locations row,
     *    record a stock_movements row of type='adjustment', and update
     *    bahan_baku.current_stock by the delta (kept consistent with global).
     */
    public function approve(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            'approval_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            DB::beginTransaction();

            // Guard: hanya manager yang boleh approve
            if (!$this->canManageOpname($outlet->schema_name, $outlet->id)) {
                DB::rollBack();
                DB::statement('SET search_path TO public');
                return response()->json(['message' => 'Anda tidak memiliki akses untuk approve stock opname'], 403);
            }

            $stockOpname = StockOpname::with('details.bahanBaku')->findOrFail($id);

            if (!$stockOpname->can_approve) {
                DB::rollBack();
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Stock opname cannot be approved'], 400);
            }

            foreach ($stockOpname->details as $detail) {
                if ($detail->physical_stock === null) {
                    continue;
                }

                $delta = (float) $detail->difference;
                if ($delta == 0) {
                    continue;
                }

                if ($detail->stock_location_id) {
                    $this->applyLocationAdjustment(
                        bahanBaku: $detail->bahanBaku,
                        locationId: (int) $detail->stock_location_id,
                        newStock: (float) $detail->physical_stock,
                        delta: $delta,
                        opnameKode: $stockOpname->kode,
                        opnameId: $stockOpname->id,
                        notes: $detail->notes,
                    );
                } else {
                    $detail->bahanBaku->adjustStock(
                        $detail->physical_stock,
                        "Stock opname {$stockOpname->kode}: " . ($detail->notes ?? 'Adjustment'),
                        Auth::id()
                    );
                }
            }

            $stockOpname->status = 'approved';
            $stockOpname->approved_by = Auth::id();
            $stockOpname->approved_at = now();
            $stockOpname->approval_notes = $request->approval_notes;
            $stockOpname->save();

            DB::commit();

            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Stock opname approved and stock adjusted',
                'data' => $stockOpname,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject stock opname
     */
    public function reject(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            'approval_notes' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            // Guard: hanya manager yang boleh reject
            if (!$this->canManageOpname($outlet->schema_name, $outlet->id)) {
                DB::statement('SET search_path TO public');
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menolak stock opname'], 403);
            }

            $stockOpname = StockOpname::findOrFail($id);

            if (!$stockOpname->can_approve) {
                return response()->json(['message' => 'Stock opname cannot be rejected'], 400);
            }

            $stockOpname->status = 'rejected';
            $stockOpname->approved_by = Auth::id();
            $stockOpname->approved_at = now();
            $stockOpname->approval_notes = $request->approval_notes;
            $stockOpname->save();

            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Stock opname rejected',
                'data' => $stockOpname,
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get report (profit/loss analysis)
     */
    public function report($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $stockOpname = StockOpname::with(['details.bahanBaku.satuan'])->findOrFail($id);

            // Hydrate location info into details (same approach as show())
            $locationIds = $stockOpname->details
                ->pluck('stock_location_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $locationMap = [];
            if (!empty($locationIds)) {
                $locationMap = DB::table('locations')
                    ->whereIn('id', $locationIds)
                    ->get()
                    ->keyBy('id');
            }

            foreach ($stockOpname->details as $detail) {
                $loc = $detail->stock_location_id && isset($locationMap[$detail->stock_location_id])
                    ? $locationMap[$detail->stock_location_id]
                    : null;
                $detail->stock_location = $loc ? [
                    'id' => (int) $loc->id,
                    'name' => $loc->name,
                    'type' => $loc->type,
                ] : null;
            }

            $profit = 0;
            $loss = 0;
            $profitItems = [];
            $lossItems = [];

            foreach ($stockOpname->details as $detail) {
                if ($detail->difference_value > 0) {
                    $profit += $detail->difference_value;
                    $profitItems[] = $detail;
                } elseif ($detail->difference_value < 0) {
                    $loss += abs($detail->difference_value);
                    $lossItems[] = $detail;
                }
            }

            DB::statement("SET search_path TO public");

            return response()->json([
                'stock_opname' => $stockOpname,
                'summary' => [
                    'total_profit' => $profit,
                    'total_loss' => $loss,
                    'net_difference' => $profit - $loss,
                    'profit_items_count' => count($profitItems),
                    'loss_items_count' => count($lossItems),
                ],
                'profit_items' => $profitItems,
                'loss_items' => $lossItems,
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Apply a per-location adjustment: upsert bahan_baku_locations, record
     * stock_movements, and keep bahan_baku.current_stock in sync by delta.
     * Caller must already have set search_path to the outlet schema.
     */
    private function applyLocationAdjustment(
        BahanBaku $bahanBaku,
        int $locationId,
        float $newStock,
        float $delta,
        string $opnameKode,
        int $opnameId,
        ?string $notes,
    ): void {
        $row = DB::table('bahan_baku_locations')
            ->where('bahan_baku_id', $bahanBaku->id)
            ->where('location_id', $locationId)
            ->first();

        if ($row) {
            DB::table('bahan_baku_locations')
                ->where('id', $row->id)
                ->update([
                    'current_stock' => $newStock,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('bahan_baku_locations')->insert([
                'bahan_baku_id' => $bahanBaku->id,
                'location_id' => $locationId,
                'current_stock' => max(0, $newStock),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('stock_movements')->insert([
            'bahan_baku_id' => $bahanBaku->id,
            'from_location_id' => $delta < 0 ? $locationId : null,
            'to_location_id' => $delta > 0 ? $locationId : null,
            'type' => 'adjustment',
            'quantity' => abs($delta),
            'notes' => "Stock opname {$opnameKode}: " . ($notes ?? 'Adjustment'),
            'reference_type' => 'stock_opname',
            'reference_id' => $opnameId,
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);

        // Keep global aggregate in sync — apply the same delta.
        $bahanBaku->current_stock = max(0, (float) $bahanBaku->current_stock + $delta);
        $bahanBaku->save();
    }
}
