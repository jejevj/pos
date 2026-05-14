<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\MembershipSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MembershipSettingController extends Controller
{
    private function authorizeOutlet($outletId)
    {
        $user = Auth::user();
        $outlet = Outlet::find($outletId);
        if (!$outlet) abort(404, 'Outlet not found');
        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) abort(403, 'Unauthorized');
        return $outlet;
    }

    /**
     * Get membership settings
     */
    public function index($outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $settings = MembershipSetting::first();
            
            if (!$settings) {
                // Create default settings if not exists
                $settings = MembershipSetting::create([
                    'point_conversion_rate' => 1000,
                    'point_per_rupiah' => 1.00,
                    'min_transaction_for_points' => 0,
                    'tiers' => [
                        ['name' => 'Silver', 'min_points' => 0, 'discount_percentage' => 0],
                        ['name' => 'Gold', 'min_points' => 1000, 'discount_percentage' => 5],
                        ['name' => 'Platinum', 'min_points' => 5000, 'discount_percentage' => 10],
                    ],
                ]);
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json($settings);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update membership settings
     */
    public function update(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'point_conversion_rate' => 'required|integer|min:1',
            'point_per_rupiah' => 'required|numeric|min:0.01',
            'point_expiry_days' => 'nullable|integer|min:1',
            'min_transaction_for_points' => 'nullable|numeric|min:0',
            'tiers' => 'required|array|min:1',
            'tiers.*.name' => 'required|string',
            'tiers.*.min_points' => 'required|integer|min:0',
            'tiers.*.discount_percentage' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $settings = MembershipSetting::first();
            
            if (!$settings) {
                $settings = MembershipSetting::create($request->all());
            } else {
                $settings->update($request->all());
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Membership settings updated successfully',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
