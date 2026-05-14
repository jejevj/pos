<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PromoController extends Controller
{
    use AuthorizesOutletAccess;


    /**
     * Get all promos
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $query = Promo::query();
            
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }
            
            if ($request->has('available_only') && $request->available_only) {
                $query->available();
            }
            
            $promos = $query->orderBy('created_at', 'desc')->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($promos);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get available promos for current time
     */
    public function available($outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $promos = Promo::available()->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($promos);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get applicable promos based on subtotal
     */
    public function applicable(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'subtotal' => 'required|numeric|min:0',
            'current_datetime' => 'nullable|date',
            'member_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $subtotal = $request->subtotal;
            $currentDateTime = $request->current_datetime ? \Carbon\Carbon::parse($request->current_datetime) : \Carbon\Carbon::now();
            $memberId = $request->member_id;
            
            // Get all promos
            $query = Promo::where('is_active', true);
            
            // Filter by member status
            if ($memberId) {
                // Show all promos (member-only and regular)
                $query->where(function($q) {
                    $q->where('is_member_only', false)
                      ->orWhere('is_member_only', true);
                });
            } else {
                // Show only non-member promos
                $query->where('is_member_only', false);
            }
            
            $promos = $query->get();
            
            // Filter applicable promos
            $applicablePromos = $promos->filter(function($promo) use ($subtotal, $currentDateTime) {
                // Check availability with client datetime
                if (!$promo->checkAvailability($currentDateTime)) {
                    return false;
                }
                
                // Check minimum purchase
                if ($subtotal < $promo->minimum_pembelian) {
                    return false;
                }
                
                return true;
            })->values();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($applicablePromos);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Validate promo code
     */
    public function validate(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'kode' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $promo = Promo::where('kode', $request->kode)->first();
            
            if (!$promo) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Kode promo tidak ditemukan'], 404);
            }

            if (!$promo->checkAvailability()) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Promo tidak tersedia saat ini'], 400);
            }

            if ($request->subtotal < $promo->minimum_pembelian) {
                DB::statement("SET search_path TO public");
                return response()->json([
                    'message' => "Minimum pembelian Rp " . number_format($promo->minimum_pembelian, 0, ',', '.')
                ], 400);
            }

            $discount = $promo->calculateDiscount($request->subtotal);
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'valid' => true,
                'promo' => $promo,
                'discount_amount' => $discount,
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create new promo
     */
    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            'tipe' => 'required|in:percentage,nominal',
            'nilai' => 'required|numeric|min:0',
            'minimum_pembelian' => 'nullable|numeric|min:0',
            'maksimum_diskon' => 'nullable|numeric|min:0',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'hari_aktif' => 'nullable|string',
            'kuota_penggunaan' => 'nullable|integer|min:1',
            'is_stackable' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $promo = Promo::create([
                'kode' => $request->kode ?? Promo::generateKode(),
                'nama' => $request->nama,
                'deskripsi' => $request->deskripsi,
                'tipe' => $request->tipe,
                'nilai' => $request->nilai,
                'minimum_pembelian' => $request->minimum_pembelian ?? 0,
                'maksimum_diskon' => $request->maksimum_diskon,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'jam_mulai' => $request->jam_mulai,
                'jam_selesai' => $request->jam_selesai,
                'hari_aktif' => $request->hari_aktif,
                'kuota_penggunaan' => $request->kuota_penggunaan,
                'is_active' => $request->is_active ?? true,
                'is_stackable' => $request->is_stackable ?? false,
                'is_member_only' => $request->is_member_only ?? false,
            ]);
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Promo created successfully',
                'data' => $promo
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get promo detail
     */
    public function show($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $promo = Promo::findOrFail($id);
            
            DB::statement("SET search_path TO public");
            
            return response()->json($promo);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update promo
     */
    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            'tipe' => 'required|in:percentage,nominal',
            'nilai' => 'required|numeric|min:0',
            'minimum_pembelian' => 'nullable|numeric|min:0',
            'maksimum_diskon' => 'nullable|numeric|min:0',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i',
            'hari_aktif' => 'nullable|string',
            'kuota_penggunaan' => 'nullable|integer|min:1',
            'is_stackable' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $promo = Promo::findOrFail($id);
            
            $promo->update([
                'nama' => $request->nama,
                'deskripsi' => $request->deskripsi,
                'tipe' => $request->tipe,
                'nilai' => $request->nilai,
                'minimum_pembelian' => $request->minimum_pembelian ?? 0,
                'maksimum_diskon' => $request->maksimum_diskon,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'jam_mulai' => $request->jam_mulai,
                'jam_selesai' => $request->jam_selesai,
                'hari_aktif' => $request->hari_aktif,
                'kuota_penggunaan' => $request->kuota_penggunaan,
                'is_active' => $request->is_active ?? $promo->is_active,
                'is_stackable' => $request->is_stackable ?? $promo->is_stackable,
                'is_member_only' => $request->is_member_only ?? $promo->is_member_only,
            ]);
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Promo updated successfully',
                'data' => $promo
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete promo
     */
    public function destroy($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $promo = Promo::findOrFail($id);
            $promo->delete();
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Promo deleted successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
