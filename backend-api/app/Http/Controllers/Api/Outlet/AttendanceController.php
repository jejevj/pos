<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    private function authorizeOutlet($outletId)
    {
        $user = Auth::user();
        $outlet = Outlet::find($outletId);
        if (!$outlet) abort(404, 'Outlet not found');
        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) abort(403, 'Unauthorized');
        // Self-heal outlets created before HR provisioning was wired into
        // Outlet::createSchema(). Idempotent — uses CREATE TABLE IF NOT EXISTS.
        $outlet->ensureHRTables();
        return $outlet;
    }

    /**
     * Resolve the authenticated global user to an outlet_user row in this
     * outlet's schema. Returns the outlet_user record (stdClass) or null.
     *
     * Assumes schema is already set on the connection (search_path).
     */
    private function resolveOutletUser($outlet)
    {
        $authUser = Auth::user();
        if (!$authUser) return null;

        return DB::table('outlet_users')
            ->where('outlet_id', $outlet->id)
            ->where('email', $authUser->email)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Return a JSON 403 response when the authenticated user is not mapped
     * to an outlet_user in this outlet. Caller must have already reset
     * search_path before invoking.
     */
    private function notOutletEmployeeResponse()
    {
        return response()->json([
            'message' => 'Akun Anda tidak terdaftar sebagai karyawan di outlet ini. '
                . 'Hubungi pemilik/manajer outlet untuk dibuatkan akun outlet_user, '
                . 'atau jalankan seeder OutletUsersSeeder untuk demo.',
            'code' => 'NOT_OUTLET_EMPLOYEE',
        ], 403);
    }

    /**
     * Get attendances with filters
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $query = DB::table('attendances')
                ->join('outlet_users', 'attendances.user_id', '=', 'outlet_users.id')
                ->select('attendances.*', 'outlet_users.name as user_name', 'outlet_users.email');

            // Resolve current outlet_user for non-superadmin scoping
            $authUser = Auth::user();
            $currentOutletUser = $this->resolveOutletUser($outlet);

            // Optional explicit filter: resolve "me" to current outlet_user
            if ($request->has('user_id')) {
                $requested = $request->user_id;
                if ($requested === 'me') {
                    if (!$currentOutletUser) {
                        DB::statement("SET search_path TO public");
                        return $this->notOutletEmployeeResponse();
                    }
                    $query->where('attendances.user_id', $currentOutletUser->id);
                } else {
                    $query->where('attendances.user_id', (int) $requested);
                }
            } elseif (!$authUser->isSuperAdmin() && $outlet->user_id !== $authUser->id) {
                // Non-admin staff: only see own attendance
                if (!$currentOutletUser) {
                    DB::statement("SET search_path TO public");
                    return $this->notOutletEmployeeResponse();
                }
                $query->where('attendances.user_id', $currentOutletUser->id);
            }

            if ($request->has('date')) {
                $query->where('attendances.date', $request->date);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('attendances.date', [$request->start_date, $request->end_date]);
            }

            if ($request->has('status')) {
                $query->where('attendances.status', $request->status);
            }

            $attendances = $query->orderBy('attendances.date', 'desc')
                ->orderBy('attendances.clock_in', 'desc')
                ->get();

            DB::statement("SET search_path TO public");

            return response()->json($attendances);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Clock in
     */
    public function clockIn(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $request->validate([
            'photo' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            // Derive outlet_user_id from authenticated global user. Client-provided
            // user_id is intentionally ignored — attendance is always for the
            // currently authenticated user, mapped to this outlet's outlet_users.
            $outletUser = $this->resolveOutletUser($outlet);
            if (!$outletUser) {
                DB::statement("SET search_path TO public");
                return $this->notOutletEmployeeResponse();
            }
            $outletUserId = $outletUser->id;

            $today = Carbon::now()->toDateString();

            // Check if already clocked in today
            $existing = DB::table('attendances')
                ->where('user_id', $outletUserId)
                ->where('date', $today)
                ->first();

            if ($existing && $existing->clock_in) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Already clocked in today'], 422);
            }

            // Get attendance settings to check radius
            $settings = DB::table('payroll_settings')->first();
            if ($settings && $settings->attendance_location_lat && $settings->attendance_location_lng) {
                $distance = $this->calculateDistance(
                    $request->latitude,
                    $request->longitude,
                    $settings->attendance_location_lat,
                    $settings->attendance_location_lng
                );

                $allowedRadius = $settings->attendance_radius ?? 100;

                if ($distance > $allowedRadius) {
                    DB::statement("SET search_path TO public");
                    return response()->json([
                        'message' => 'You are outside the allowed attendance radius. Distance: ' . round($distance) . 'm, Allowed: ' . $allowedRadius . 'm',
                        'distance' => round($distance),
                        'allowed_radius' => $allowedRadius
                    ], 422);
                }
            }

            $clockInTime = Carbon::now();
            $location = json_encode([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'accuracy' => $request->accuracy,
                'timestamp' => $clockInTime->toIso8601String()
            ]);

            if ($existing) {
                DB::table('attendances')
                    ->where('id', $existing->id)
                    ->update([
                        'clock_in' => $clockInTime,
                        'clock_in_photo' => $request->photo,
                        'clock_in_location' => $location,
                        'clock_in_notes' => $request->notes,
                        'status' => 'present',
                        'updated_at' => now(),
                    ]);

                $attendance = DB::table('attendances')->where('id', $existing->id)->first();
            } else {
                $id = DB::table('attendances')->insertGetId([
                    'user_id' => $outletUserId,
                    'date' => $today,
                    'clock_in' => $clockInTime,
                    'clock_in_photo' => $request->photo,
                    'clock_in_location' => $location,
                    'clock_in_notes' => $request->notes,
                    'status' => 'present',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $attendance = DB::table('attendances')->where('id', $id)->first();
            }

            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Clock in successful',
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Clock out
     */
    public function clockOut(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $request->validate([
            'photo' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $outletUser = $this->resolveOutletUser($outlet);
            if (!$outletUser) {
                DB::statement("SET search_path TO public");
                return $this->notOutletEmployeeResponse();
            }
            $outletUserId = $outletUser->id;

            $today = Carbon::now()->toDateString();

            $attendance = DB::table('attendances')
                ->where('user_id', $outletUserId)
                ->where('date', $today)
                ->first();

            if (!$attendance || !$attendance->clock_in) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'No clock in record found'], 422);
            }

            if ($attendance->clock_out) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Already clocked out'], 422);
            }

            // Get attendance settings to check radius
            $settings = DB::table('payroll_settings')->first();
            if ($settings && $settings->attendance_location_lat && $settings->attendance_location_lng) {
                $distance = $this->calculateDistance(
                    $request->latitude,
                    $request->longitude,
                    $settings->attendance_location_lat,
                    $settings->attendance_location_lng
                );

                $allowedRadius = $settings->attendance_radius ?? 100;

                if ($distance > $allowedRadius) {
                    DB::statement("SET search_path TO public");
                    return response()->json([
                        'message' => 'You are outside the allowed attendance radius. Distance: ' . round($distance) . 'm, Allowed: ' . $allowedRadius . 'm',
                        'distance' => round($distance),
                        'allowed_radius' => $allowedRadius
                    ], 422);
                }
            }

            $clockOutTime = Carbon::now();
            $clockInTime = Carbon::parse($attendance->clock_in);

            $workMinutes = $clockOutTime->diffInMinutes($clockInTime);
            $workHours = round($workMinutes / 60, 2);

            $overtimeHours = max(0, $workHours - 8);

            $location = json_encode([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'accuracy' => $request->accuracy,
                'timestamp' => $clockOutTime->toIso8601String()
            ]);

            DB::table('attendances')
                ->where('id', $attendance->id)
                ->update([
                    'clock_out' => $clockOutTime,
                    'clock_out_photo' => $request->photo,
                    'clock_out_location' => $location,
                    'clock_out_notes' => $request->notes,
                    'work_hours' => $workHours,
                    'overtime_hours' => $overtimeHours,
                    'updated_at' => now(),
                ]);

            $updatedAttendance = DB::table('attendances')->where('id', $attendance->id)->first();

            DB::statement("SET search_path TO public");

            return response()->json([
                'message' => 'Clock out successful',
                'data' => $updatedAttendance
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get today's attendance status for a user.
     *
     * `$userId` may be the literal string "me" to resolve from the
     * authenticated user, or a numeric outlet_user id.
     */
    public function getTodayStatus(Request $request, $outletId, $userId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            if ($userId === 'me' || !is_numeric($userId)) {
                $outletUser = $this->resolveOutletUser($outlet);
                if (!$outletUser) {
                    DB::statement("SET search_path TO public");
                    return response()->json([
                        'has_clocked_in' => false,
                        'has_clocked_out' => false,
                        'attendance' => null,
                        'is_outlet_employee' => false,
                    ]);
                }
                $userId = $outletUser->id;
            }

            $today = Carbon::now()->toDateString();

            $attendance = DB::table('attendances')
                ->where('user_id', $userId)
                ->where('date', $today)
                ->first();

            DB::statement("SET search_path TO public");

            return response()->json([
                'has_clocked_in' => $attendance && $attendance->clock_in ? true : false,
                'has_clocked_out' => $attendance && $attendance->clock_out ? true : false,
                'attendance' => $attendance,
                'is_outlet_employee' => true,
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get attendance summary
     */
    public function getSummary(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();

            $summary = DB::table('attendances')
                ->select(
                    DB::raw('COUNT(*) as total_records'),
                    DB::raw("COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count"),
                    DB::raw("COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count"),
                    DB::raw("COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count"),
                    DB::raw("COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_count"),
                    DB::raw('SUM(work_hours) as total_work_hours'),
                    DB::raw('SUM(overtime_hours) as total_overtime_hours')
                )
                ->whereBetween('date', [$startDate, $endDate])
                ->first();

            DB::statement("SET search_path TO public");

            return response()->json($summary);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * Returns distance in meters
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Earth radius in meters

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
