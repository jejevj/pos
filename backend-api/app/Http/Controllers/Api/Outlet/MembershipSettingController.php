<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\MembershipSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MembershipSettingController extends Controller
{
    use AuthorizesOutletAccess;


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

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $existing = MembershipSetting::first();

            // Merge incoming payload with existing values so partial updates
            // (e.g. UI form that doesn't expose every column) don't fail validation
            // on fields the client didn't intend to change.
            $defaults = [
                'point_conversion_rate' => 1000,
                'point_per_rupiah' => 1.00,
                'point_expiry_days' => null,
                'min_transaction_for_points' => 0,
                'tiers' => [
                    ['name' => 'Silver', 'min_points' => 0, 'discount_percentage' => 0],
                    ['name' => 'Gold', 'min_points' => 1000, 'discount_percentage' => 5],
                    ['name' => 'Platinum', 'min_points' => 5000, 'discount_percentage' => 10],
                ],
            ];

            $base = $existing ? $existing->only(array_keys($defaults)) : [];
            $incoming = $request->only(array_keys($defaults));
            $merged = array_merge($base, $incoming);

            // Fall back to defaults for required fields that are still missing/null
            // after merge (handles legacy rows with NULL columns or UIs that don't
            // expose every field).
            $payload = $merged;
            foreach (['point_conversion_rate', 'point_per_rupiah', 'tiers'] as $required) {
                if (!array_key_exists($required, $payload) || $payload[$required] === null
                    || (is_array($payload[$required]) && count($payload[$required]) === 0)) {
                    $payload[$required] = $defaults[$required];
                }
            }

            $validator = Validator::make($payload, [
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
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            if (!$existing) {
                $settings = MembershipSetting::create($payload);
            } else {
                $existing->update($payload);
                $settings = $existing;
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
