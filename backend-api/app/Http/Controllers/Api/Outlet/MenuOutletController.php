<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\MenuOutlet;
use App\Models\MenuBahanBaku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MenuOutletController extends Controller
{
    use AuthorizesOutletAccess;


    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $query = MenuOutlet::with(['kategori', 'bahanBaku.bahanBaku.satuan']);

            if ($request->kategori_id) $query->where('kategori_id', $request->kategori_id);
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('nama', 'ilike', "%{$request->search}%")
                      ->orWhere('kode', 'ilike', "%{$request->search}%");
                });
            }
            if ($request->has('is_available')) $query->where('is_available', $request->boolean('is_available'));

            $data = $query->orderBy('nama')->get();
            DB::statement("SET search_path TO public");
            return response()->json($data);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $validator = Validator::make($request->all(), [
            'nama'        => 'required|string|max:200',
            'kategori_id' => 'required|integer',
            'station_id'  => 'nullable|integer',
            'deskripsi'   => 'nullable|string',
            'harga_jual'  => 'required|numeric|min:0',
            'harga_modal' => 'nullable|numeric|min:0',
            'apply_fixed_cost' => 'boolean',
            'gambar_url'  => 'nullable|string|max:255',
            'is_available'=> 'boolean',
            'is_active'   => 'boolean',
            'bahan_baku'  => 'nullable|array',
            'bahan_baku.*.bahan_baku_id' => 'required|integer',
            'bahan_baku.*.satuan_id'     => 'required|integer',
            'bahan_baku.*.jumlah'        => 'required|numeric|min:0.0001',
            'bahan_baku.*.keterangan'    => 'nullable|string',
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $data = $validator->validated();
            $bahanBakuItems = $data['bahan_baku'] ?? [];
            unset($data['bahan_baku']);

            $data['kode'] = MenuOutlet::generateKode($data['kategori_id']);
            $data['harga_modal'] = $this->calculateHargaModal($bahanBakuItems, $outletId, $data['apply_fixed_cost'] ?? true);

            $menu = MenuOutlet::create($data);

            foreach ($bahanBakuItems as $item) {
                MenuBahanBaku::create(array_merge($item, ['menu_id' => $menu->id]));
            }

            $menu->load(['kategori', 'bahanBaku.bahanBaku.satuan', 'bahanBaku.satuan']);
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Menu created successfully', 'data' => $menu], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $menu = MenuOutlet::with(['kategori', 'bahanBaku.bahanBaku.satuan', 'bahanBaku.satuan'])->findOrFail($id);
            DB::statement("SET search_path TO public");
            return response()->json($menu);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        $validator = Validator::make($request->all(), [
            'nama'        => 'string|max:200',
            'kategori_id' => 'integer',
            'station_id'  => 'nullable|integer',
            'deskripsi'   => 'nullable|string',
            'harga_jual'  => 'numeric|min:0',
            'harga_modal' => 'nullable|numeric|min:0',
            'apply_fixed_cost' => 'boolean',
            'gambar_url'  => 'nullable|string|max:255',
            'is_available'=> 'boolean',
            'is_active'   => 'boolean',
            'bahan_baku'  => 'nullable|array',
            'bahan_baku.*.bahan_baku_id' => 'required|integer',
            'bahan_baku.*.satuan_id'     => 'required|integer',
            'bahan_baku.*.jumlah'        => 'required|numeric|min:0.0001',
            'bahan_baku.*.keterangan'    => 'nullable|string',
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $menu = MenuOutlet::findOrFail($id);
            $data = $validator->validated();
            $bahanBakuItems = $data['bahan_baku'] ?? null;
            unset($data['bahan_baku']);

            if ($bahanBakuItems !== null) {
                $data['harga_modal'] = $this->calculateHargaModal($bahanBakuItems, $outletId, $data['apply_fixed_cost'] ?? $menu->apply_fixed_cost);
                MenuBahanBaku::where('menu_id', $menu->id)->delete();
                foreach ($bahanBakuItems as $item) {
                    MenuBahanBaku::create(array_merge($item, ['menu_id' => $menu->id]));
                }
            }

            $menu->update($data);
            $menu->load(['kategori', 'bahanBaku.bahanBaku.satuan', 'bahanBaku.satuan']);
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Menu updated successfully', 'data' => $menu]);
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
            $menu = MenuOutlet::findOrFail($id);
            MenuBahanBaku::where('menu_id', $menu->id)->delete();
            $menu->delete();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Menu deleted successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Check availability of a menu based on ingredient stock
     */
    public function checkAvailability($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            $menu = MenuOutlet::with(['bahanBaku.bahanBaku.satuan'])->findOrFail($id);
            
            $details = [];
            $minQuantity = PHP_INT_MAX;
            
            foreach ($menu->bahanBaku as $ingredient) {
                $required = $ingredient->jumlah;
                $available = $ingredient->bahanBaku->current_stock;
                $canMake = $required > 0 ? floor($available / $required) : 0;
                
                $details[] = [
                    'ingredient' => $ingredient->bahanBaku->nama,
                    'required' => $required,
                    'available' => $available,
                    'unit' => $ingredient->bahanBaku->satuan->singkatan,
                    'can_make' => $canMake,
                    'sufficient' => $available >= $required
                ];
                
                $minQuantity = min($minQuantity, $canMake);
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'menu' => $menu->nama,
                'available_quantity' => $minQuantity === PHP_INT_MAX ? 0 : $minQuantity,
                'can_be_made' => $minQuantity > 0,
                'ingredients' => $details
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function calculateHargaModal(array $bahanBakuItems, $outletId, $applyFixedCost = true): float
    {
        $total = 0;
        foreach ($bahanBakuItems as $item) {
            $bb = \App\Models\BahanBaku::find($item['bahan_baku_id']);
            if ($bb) {
                // Use harga_per_satuan_dasar which handles unit conversion automatically
                $total += $bb->harga_per_satuan_dasar * $item['jumlah'];
            }
        }
        
        // Add fixed cost from outlet if apply_fixed_cost is true
        if ($applyFixedCost) {
            $outlet = \App\Models\Outlet::find($outletId);
            if ($outlet) {
                if ($outlet->fixed_cost_type === 'percentage' && $outlet->fixed_cost_percentage > 0) {
                    $fixedCost = $total * ($outlet->fixed_cost_percentage / 100);
                    $total += $fixedCost;
                } elseif ($outlet->fixed_cost_type === 'nominal' && $outlet->fixed_cost_nominal > 0) {
                    $total += $outlet->fixed_cost_nominal;
                }
            }
        }
        
        return $total;
    }
}
