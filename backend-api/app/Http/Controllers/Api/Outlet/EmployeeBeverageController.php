<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeBeverageController extends Controller
{
    /**
     * Get beverage settings
     */
    public function getSettings(Request $request, $outletId)
    {
        try {
            $schemaName = $this->getOutletSchema($outletId);
            DB::statement("SET search_path TO {$schemaName}, public");

            $settings = DB::table('employee_beverage_settings')->first();
            
            if (!$settings) {
                // Create default settings if not exists
                $settingsId = DB::table('employee_beverage_settings')->insertGetId([
                    'daily_quota' => 1,
                    'is_active' => true,
                    'reset_time' => '00:00:00',
                    'notes' => 'Default settings',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $settings = DB::table('employee_beverage_settings')->where('id', $settingsId)->first();
            }

            DB::statement("SET search_path TO public");
            return response()->json($settings);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to fetch settings', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update beverage settings
     */
    public function updateSettings(Request $request, $outletId)
    {
        $validator = Validator::make($request->all(), [
            'daily_quota' => 'required|integer|min:0|max:10',
            'is_active' => 'required|boolean',
            'reset_time' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $schemaName = $this->getOutletSchema($outletId);
            DB::statement("SET search_path TO {$schemaName}, public");

            $settings = DB::table('employee_beverage_settings')->first();
            
            if ($settings) {
                DB::table('employee_beverage_settings')
                    ->where('id', $settings->id)
                    ->update([
                        'daily_quota' => $request->daily_quota,
                        'is_active' => $request->is_active,
                        'reset_time' => $request->reset_time ?? '00:00:00',
                        'notes' => $request->notes,
                        'updated_at' => now()
                    ]);
            } else {
                DB::table('employee_beverage_settings')->insert([
                    'daily_quota' => $request->daily_quota,
                    'is_active' => $request->is_active,
                    'reset_time' => $request->reset_time ?? '00:00:00',
                    'notes' => $request->notes,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Settings updated successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to update settings', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get allowed beverages
     */
    public function getAllowedBeverages(Request $request, $outletId)
    {
        try {
            $schemaName = $this->getOutletSchema($outletId);
            DB::statement("SET search_path TO {$schemaName}, public");

            $beverages = DB::table('employee_allowed_beverages as eab')
                ->join('menu as m', 'eab.menu_id', '=', 'm.id')
                ->leftJoin('kategori_menu as km', 'm.kategori_id', '=', 'km.id')
                ->select(
                    'eab.id',
                    'eab.menu_id',
                    'eab.is_active',
                    'eab.created_at',
                    'm.nama as menu_name',
                    'm.gambar_url as menu_image',
                    'km.nama as category_name',
                    'm.harga_jual as price'
                )
                ->orderBy('km.nama')
                ->orderBy('m.nama')
                ->get();

            DB::statement("SET search_path TO public");
            return response()->json($beverages);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to fetch allowed beverages', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Add allowed beverage
     */
    public function addAllowedBeverage(Request $request, $outletId)
    {
        $validator = Validator::make($request->all(), [
            'menu_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $schemaName = $this->getOutletSchema($outletId);
            DB::statement("SET search_path TO {$schemaName}, public");

            // Check if already exists
            $exists = DB::table('employee_allowed_beverages')
                ->where('menu_id', $request->menu_id)
                ->exists();

            if ($exists) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Beverage already in allowed list'], 422);
            }

            DB::table('employee_allowed_beverages')->insert([
                'menu_id' => $request->menu_id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Beverage added successfully'], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to add beverage', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove allowed beverage
     */
    public function removeAllowedBeverage(Request $request, $outletId, $id)
    {
        try {
            $schemaName = $this->getOutletSchema($outletId);
            DB::statement("SET search_path TO {$schemaName}, public");

            DB::table('employee_allowed_beverages')->where('id', $id)->delete();

            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Beverage removed successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to remove beverage', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get employee claims (for today or specific date)
     */
    public function getEmployeeClaims(Request $request, $outletId)
    {
        try {
            $schemaName = $this->getOutletSchema($outletId);
            DB::statement("SET search_path TO {$schemaName}, public");

            $date = $request->query('date', date('Y-m-d'));
            $userId = $request->query('user_id');

            $query = DB::table('employee_beverage_claims as ebc')
                ->join('outlet_users as u', 'ebc.user_id', '=', 'u.id')
                ->join('menu as m', 'ebc.menu_id', '=', 'm.id')
                ->select(
                    'ebc.id',
                    'ebc.user_id',
                    'ebc.menu_id',
                    'ebc.claimed_at',
                    'ebc.claimed_date',
                    'ebc.notes',
                    'u.name as employee_name',
                    'm.nama as menu_name',
                    'm.gambar_url as menu_image'
                )
                ->where('ebc.claimed_date', $date);

            if ($userId) {
                $query->where('ebc.user_id', $userId);
            }

            $claims = $query->orderBy('ebc.claimed_at', 'desc')->get();

            DB::statement("SET search_path TO public");
            return response()->json($claims);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to fetch claims', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Claim beverage
     */
    public function claimBeverage(Request $request, $outletId)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'menu_id' => 'required|integer',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $schemaName = $this->getOutletSchema($outletId);
            DB::statement("SET search_path TO {$schemaName}, public");

            // Get settings
            $settings = DB::table('employee_beverage_settings')->first();
            
            if (!$settings || !$settings->is_active) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Employee beverage allowance is not active'], 422);
            }

            // Check if menu is in allowed list
            $allowed = DB::table('employee_allowed_beverages')
                ->where('menu_id', $request->menu_id)
                ->where('is_active', true)
                ->exists();

            if (!$allowed) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'This beverage is not in the allowed list'], 422);
            }

            // Check today's claims
            $today = date('Y-m-d');
            $claimsToday = DB::table('employee_beverage_claims')
                ->where('user_id', $request->user_id)
                ->where('claimed_date', $today)
                ->count();

            if ($claimsToday >= $settings->daily_quota) {
                DB::statement("SET search_path TO public");
                return response()->json([
                    'message' => "Daily quota reached. Limit: {$settings->daily_quota} per day"
                ], 422);
            }

            // Create claim
            DB::table('employee_beverage_claims')->insert([
                'user_id' => $request->user_id,
                'menu_id' => $request->menu_id,
                'claimed_at' => now(),
                'claimed_date' => $today,
                'notes' => $request->notes,
                'created_by' => $request->user()->id ?? null
            ]);

            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Beverage claimed successfully'], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to claim beverage', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get employee quota status
     */
    public function getEmployeeQuotaStatus(Request $request, $outletId, $userId)
    {
        try {
            $schemaName = $this->getOutletSchema($outletId);
            DB::statement("SET search_path TO {$schemaName}, public");

            $settings = DB::table('employee_beverage_settings')->first();
            $today = date('Y-m-d');
            
            $claimsToday = DB::table('employee_beverage_claims')
                ->where('user_id', $userId)
                ->where('claimed_date', $today)
                ->count();

            $status = [
                'user_id' => $userId,
                'date' => $today,
                'daily_quota' => $settings->daily_quota ?? 1,
                'claimed' => $claimsToday,
                'remaining' => max(0, ($settings->daily_quota ?? 1) - $claimsToday),
                'can_claim' => $claimsToday < ($settings->daily_quota ?? 1)
            ];

            DB::statement("SET search_path TO public");
            return response()->json($status);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to fetch quota status', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get statistics
     */
    public function getStatistics(Request $request, $outletId)
    {
        try {
            $schemaName = $this->getOutletSchema($outletId);
            DB::statement("SET search_path TO {$schemaName}, public");

            $today = date('Y-m-d');
            $startDate = $request->query('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->query('end_date', $today);

            // Total claims today
            $claimsToday = DB::table('employee_beverage_claims')
                ->where('claimed_date', $today)
                ->count();

            // Total claims in period
            $claimsPeriod = DB::table('employee_beverage_claims')
                ->whereBetween('claimed_date', [$startDate, $endDate])
                ->count();

            // Most claimed beverages
            $topBeverages = DB::table('employee_beverage_claims as ebc')
                ->join('menu as m', 'ebc.menu_id', '=', 'm.id')
                ->select('m.nama as menu_name', DB::raw('COUNT(*) as claim_count'))
                ->whereBetween('ebc.claimed_date', [$startDate, $endDate])
                ->groupBy('m.id', 'm.nama')
                ->orderBy('claim_count', 'desc')
                ->limit(5)
                ->get();

            // Active employees claiming
            $activeEmployees = DB::table('employee_beverage_claims')
                ->where('claimed_date', $today)
                ->distinct('user_id')
                ->count('user_id');

            $stats = [
                'claims_today' => $claimsToday,
                'claims_period' => $claimsPeriod,
                'active_employees_today' => $activeEmployees,
                'top_beverages' => $topBeverages,
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];

            DB::statement("SET search_path TO public");
            return response()->json($stats);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => 'Failed to fetch statistics', 'error' => $e->getMessage()], 500);
        }
    }

    private function getOutletSchema($outletId)
    {
        $outlet = DB::table('outlets')->where('id', $outletId)->first();
        if (!$outlet) {
            throw new \Exception('Outlet not found');
        }
        return $outlet->schema_name;
    }
}
