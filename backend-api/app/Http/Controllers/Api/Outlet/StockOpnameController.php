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
     * Get all stock opname records
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $query = StockOpname::query();
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Don't load details for list view to improve performance
            $stockOpnames = $query->orderBy('created_at', 'desc')->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($stockOpnames);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create new stock opname schedule
     */
    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'pic_name' => 'required|string|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $stockOpname = StockOpname::create([
                'kode' => StockOpname::generateKode(),
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'status' => 'draft',
                'pic_name' => $request->pic_name,
                'pic_user_id' => Auth::id(),
                'notes' => $request->notes,
                'created_by' => Auth::id(),
            ]);

            // Create details for all active materials
            $materials = BahanBaku::where('is_active', true)->get();
            
            foreach ($materials as $material) {
                StockOpnameDetail::create([
                    'stock_opname_id' => $stockOpname->id,
                    'bahan_baku_id' => $material->id,
                    'system_stock' => $material->current_stock,
                ]);
            }

            // Load details and update total_items
            $stockOpname->load('details');
            $stockOpname->total_items = $stockOpname->details->count();
            $stockOpname->save();
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Stock opname created successfully',
                'data' => $stockOpname
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get stock opname detail
     */
    public function show($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $stockOpname = StockOpname::with(['details.bahanBaku.satuan'])->findOrFail($id);
            
            // Add can_submit attribute after loading details
            $stockOpname->can_submit = $stockOpname->canSubmit();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($stockOpname);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update stock opname (fill physical stock)
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
            
            if (!$stockOpname->is_editable) {
                DB::rollBack();
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Stock opname cannot be edited in current status'], 400);
            }

            // Update each detail from the loaded collection
            foreach ($request->details as $detailData) {
                $detail = $stockOpname->details->firstWhere('id', $detailData['id']);
                
                if ($detail) {
                    // Update physical stock and notes
                    $detail->physical_stock = isset($detailData['physical_stock']) && $detailData['physical_stock'] !== '' 
                        ? (float) $detailData['physical_stock'] 
                        : null;
                    $detail->notes = $detailData['notes'] ?? null;
                    
                    // Calculate difference if physical_stock is provided and valid
                    if ($detail->physical_stock !== null && is_numeric($detail->physical_stock)) {
                        $detail->difference = $detail->physical_stock - $detail->system_stock;
                        
                        // Calculate value
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

            // Update status to in_progress if still draft
            if ($stockOpname->status === 'draft') {
                $stockOpname->status = 'in_progress';
                $stockOpname->save();
            }

            // Calculate total (details already loaded)
            $stockOpname->calculateTotalDifferenceValue();
            
            DB::commit();
            
            // Add can_submit attribute
            $stockOpname->can_submit = $stockOpname->canSubmit();
            
            // Reload with satuan for response
            $stockOpname->load(['details.bahanBaku.satuan']);
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Stock opname updated successfully',
                'data' => $stockOpname
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
            
            // Check if can submit using method instead of accessor
            if (!$stockOpname->canSubmit()) {
                return response()->json(['message' => 'Stock opname cannot be submitted'], 400);
            }

            $stockOpname->status = 'submitted';
            $stockOpname->save();
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Stock opname submitted for review',
                'data' => $stockOpname
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve stock opname
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
            
            $stockOpname = StockOpname::with('details.bahanBaku')->findOrFail($id);
            
            if (!$stockOpname->can_approve) {
                return response()->json(['message' => 'Stock opname cannot be approved'], 400);
            }

            // Apply stock adjustments
            foreach ($stockOpname->details as $detail) {
                if ($detail->difference != 0 && $detail->physical_stock !== null) {
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
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Stock opname approved and stock adjusted',
                'data' => $stockOpname
            ]);
        } catch (\Exception $e) {
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
                'data' => $stockOpname
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
}
