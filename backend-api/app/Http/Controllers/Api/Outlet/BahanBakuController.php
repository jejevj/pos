<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\BahanBaku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BahanBakuController extends Controller
{
    use AuthorizesOutletAccess;

    /**
     * Get all bahan baku for an outlet
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $query = BahanBaku::with(['kategori', 'satuan', 'satuanPembelian', 'supplier']);

            // Filter by category
            if ($request->has('kategori_id')) {
                $query->byKategori($request->kategori_id);
            }

            // Filter by stock status
            if ($request->has('stock_status')) {
                if ($request->stock_status === 'low_stock') {
                    $query->lowStock();
                } elseif ($request->stock_status === 'active') {
                    $query->active();
                }
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nama', 'ilike', "%{$search}%")
                      ->orWhere('kode', 'ilike', "%{$search}%");
                });
            }

            $bahanBaku = $query->orderBy('nama')->get();
            
            DB::statement("SET search_path TO public");

            return response()->json($bahanBaku);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to fetch bahan baku',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new bahan baku
     */
    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:100',
                'kategori_id' => 'required|integer',
                'satuan_id' => 'required|integer',
                'satuan_pembelian_id' => 'nullable|integer',
                'jumlah_per_unit_pembelian' => 'nullable|numeric|min:0.0001',
                'supplier_id' => 'nullable|integer',
                'harga_beli' => 'required|numeric|min:0',
                'minimum_stock' => 'required|numeric|min:0',
                'current_stock' => 'nullable|numeric|min:0',
                'lokasi_penyimpanan' => 'nullable|string|max:100',
                'expired_date' => 'nullable|date',
                'gambar_url' => 'nullable|string|max:255',
                'deskripsi' => 'nullable|string',
                'is_active' => 'boolean',
                'defers_on_bon' => 'boolean',
            ]);

            if ($validator->fails()) {
                DB::statement("SET search_path TO public");
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Ensure column exists
            if (!DB::getSchemaBuilder()->hasColumn('bahan_baku', 'defers_on_bon')) {
                DB::statement("ALTER TABLE bahan_baku ADD COLUMN defers_on_bon BOOLEAN DEFAULT FALSE");
            }

            $data = $validator->validated();
            $data['kode'] = BahanBaku::generateKode($data['kategori_id']);
            $data['current_stock'] = $data['current_stock'] ?? 0;
            
            $bahanBaku = BahanBaku::create($data);
            
            // If initial stock > 0, record it
            if ($bahanBaku->current_stock > 0) {
                $bahanBaku->addStock(
                    $bahanBaku->current_stock,
                    'Initial stock',
                    'initial',
                    null,
                    $user->id
                );
            }
            
            $bahanBaku->load(['kategori', 'satuan', 'satuanPembelian', 'supplier']);
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Bahan baku created successfully',
                'data' => $bahanBaku
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to create bahan baku',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific bahan baku
     */
    public function show($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $bahanBaku = BahanBaku::with(['kategori', 'satuan', 'satuanPembelian', 'supplier'])->find($id);
            
            DB::statement("SET search_path TO public");

            if (!$bahanBaku) {
                return response()->json(['message' => 'Bahan baku not found'], 404);
            }

            return response()->json($bahanBaku);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to fetch bahan baku',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a bahan baku
     */
    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $validator = Validator::make($request->all(), [
                'nama' => 'string|max:100',
                'kategori_id' => 'integer',
                'satuan_id' => 'integer',
                'satuan_pembelian_id' => 'nullable|integer',
                'jumlah_per_unit_pembelian' => 'nullable|numeric|min:0.0001',
                'supplier_id' => 'nullable|integer',
                'harga_beli' => 'numeric|min:0',
                'minimum_stock' => 'numeric|min:0',
                'lokasi_penyimpanan' => 'nullable|string|max:100',
                'expired_date' => 'nullable|date',
                'gambar_url' => 'nullable|string|max:255',
                'deskripsi' => 'nullable|string',
                'is_active' => 'boolean',
                'defers_on_bon' => 'boolean',
            ]);

            if ($validator->fails()) {
                DB::statement("SET search_path TO public");
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bahanBaku = BahanBaku::find($id);
            
            if (!$bahanBaku) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Bahan baku not found'], 404);
            }

            $bahanBaku->update($validator->validated());
            $bahanBaku->load(['kategori', 'satuan', 'satuanPembelian', 'supplier']);
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Bahan baku updated successfully',
                'data' => $bahanBaku
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to update bahan baku',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a bahan baku
     */
    public function destroy($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $bahanBaku = BahanBaku::find($id);
            
            if (!$bahanBaku) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Bahan baku not found'], 404);
            }

            $bahanBaku->delete();
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Bahan baku deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to delete bahan baku',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add stock to bahan baku
     */
    public function addStock(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $bahanBaku = BahanBaku::find($id);
            
            if (!$bahanBaku) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Bahan baku not found'], 404);
            }

            $bahanBaku->addStock(
                $request->quantity,
                $request->notes,
                $request->reference_type ?? 'manual',
                $request->reference_id,
                $user->id
            );

            $bahanBaku->load(['kategori', 'satuan', 'satuanPembelian', 'supplier']);
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Stock added successfully',
                'data' => $bahanBaku
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to add stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reduce stock from bahan baku
     */
    public function reduceStock(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $bahanBaku = BahanBaku::find($id);
            
            if (!$bahanBaku) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Bahan baku not found'], 404);
            }

            $bahanBaku->reduceStock(
                $request->quantity,
                $request->notes,
                $request->reference_type ?? 'manual',
                $request->reference_id,
                $user->id
            );

            $bahanBaku->load(['kategori', 'satuan', 'satuanPembelian', 'supplier']);
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Stock reduced successfully',
                'data' => $bahanBaku
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to reduce stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adjust stock of bahan baku
     */
    public function adjustStock(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            'new_stock' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $bahanBaku = BahanBaku::find($id);
            
            if (!$bahanBaku) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Bahan baku not found'], 404);
            }

            $bahanBaku->adjustStock(
                $request->new_stock,
                $request->notes,
                $user->id
            );

            $bahanBaku->load(['kategori', 'satuan', 'satuanPembelian', 'supplier']);
            
            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Stock adjusted successfully',
                'data' => $bahanBaku
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to adjust stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock history for a bahan baku
     */
    public function stockHistory($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $bahanBaku = BahanBaku::find($id);
            
            if (!$bahanBaku) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Bahan baku not found'], 404);
            }

            $history = $bahanBaku->stockHistory()
                ->orderBy('created_at', 'desc')
                ->get();
            
            DB::statement("SET search_path TO public");

            return response()->json($history);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json([
                'message' => 'Failed to fetch stock history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
