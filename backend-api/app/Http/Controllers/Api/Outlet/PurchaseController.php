<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PurchaseController extends Controller
{
    private function authorizeOutlet($outletId)
    {
        $outlet = DB::table('outlets')->where('id', $outletId)->first();
        
        if (!$outlet) {
            abort(404, 'Outlet not found');
        }
        
        return $outlet;
    }

    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $query = DB::table('purchases')
                ->select('purchases.*')
                ->orderBy('purchases.purchase_date', 'desc')
                ->orderBy('purchases.id', 'desc');
            
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('purchases.purchase_date', [$request->start_date, $request->end_date]);
            }
            
            $purchases = $query->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($purchases);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'supplier_id' => 'nullable|integer',
            'supplier_name' => 'nullable|string|max:255',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.bahan_baku_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'payment_proof' => 'nullable|image|max:5120',
            'notes' => 'nullable|string',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            DB::beginTransaction();
            
            // Generate purchase code
            $lastPurchase = DB::table('purchases')
                ->whereYear('purchase_date', date('Y', strtotime($request->purchase_date)))
                ->orderBy('id', 'desc')
                ->first();
            
            $year = date('Y', strtotime($request->purchase_date));
            $month = date('m', strtotime($request->purchase_date));
            $sequence = $lastPurchase ? (intval(substr($lastPurchase->purchase_code, -4)) + 1) : 1;
            $purchaseCode = "PUR-{$year}{$month}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            
            // Handle payment proof upload
            $paymentProofUrl = null;
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs("outlets/{$outletId}/purchases", $filename, 'public');
                $paymentProofUrl = Storage::url($path);
            }
            
            // Calculate total
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }
            
            // Insert purchase
            $purchaseId = DB::table('purchases')->insertGetId([
                'purchase_code' => $purchaseCode,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => $request->supplier_name,
                'purchase_date' => $request->purchase_date,
                'total_amount' => $totalAmount,
                'payment_method' => $request->payment_method,
                'payment_proof_url' => $paymentProofUrl,
                'notes' => $request->notes,
                'status' => 'completed',
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Insert purchase items and update stock
            foreach ($request->items as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];
                
                DB::table('purchase_items')->insert([
                    'purchase_id' => $purchaseId,
                    'bahan_baku_id' => $item['bahan_baku_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $subtotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Update stock
                DB::table('bahan_baku')
                    ->where('id', $item['bahan_baku_id'])
                    ->increment('stok', $item['quantity']);
                
                // Record stock history
                DB::table('stock_history')->insert([
                    'bahan_baku_id' => $item['bahan_baku_id'],
                    'type' => 'in',
                    'quantity' => $item['quantity'],
                    'reference_type' => 'purchase',
                    'reference_id' => $purchaseId,
                    'notes' => "Purchase: {$purchaseCode}",
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                ]);
            }
            
            DB::commit();
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Purchase created successfully',
                'purchase_code' => $purchaseCode
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $purchase = DB::table('purchases')
                ->where('purchases.id', $id)
                ->select('purchases.*')
                ->first();
            
            if (!$purchase) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Purchase not found'], 404);
            }
            
            $items = DB::table('purchase_items')
                ->join('bahan_baku', 'purchase_items.bahan_baku_id', '=', 'bahan_baku.id')
                ->leftJoin('satuan', 'bahan_baku.satuan_id', '=', 'satuan.id')
                ->where('purchase_items.purchase_id', $id)
                ->select(
                    'purchase_items.*',
                    'bahan_baku.nama as bahan_baku_name',
                    'satuan.nama as satuan_name'
                )
                ->get();
            
            $purchase->items = $items;
            
            DB::statement("SET search_path TO public");
            
            return response()->json($purchase);
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
            DB::beginTransaction();
            
            $purchase = DB::table('purchases')->where('id', $id)->first();
            
            if (!$purchase) {
                DB::rollBack();
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Purchase not found'], 404);
            }
            
            // Get items to reverse stock
            $items = DB::table('purchase_items')->where('purchase_id', $id)->get();
            
            foreach ($items as $item) {
                // Reverse stock
                DB::table('bahan_baku')
                    ->where('id', $item->bahan_baku_id)
                    ->decrement('stok', $item->quantity);
                
                // Record stock history
                DB::table('stock_history')->insert([
                    'bahan_baku_id' => $item->bahan_baku_id,
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'reference_type' => 'purchase_delete',
                    'reference_id' => $id,
                    'notes' => "Deleted purchase: {$purchase->purchase_code}",
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                ]);
            }
            
            // Delete payment proof if exists
            if ($purchase->payment_proof_url) {
                $path = str_replace('/storage/', '', $purchase->payment_proof_url);
                Storage::disk('public')->delete($path);
            }
            
            // Delete purchase (items will be cascade deleted)
            DB::table('purchases')->where('id', $id)->delete();
            
            DB::commit();
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Purchase deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
