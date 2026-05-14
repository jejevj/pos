<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShiftController extends Controller
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
     * Get all shifts
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $shifts = DB::table('shifts')
                ->where('is_active', true)
                ->orderBy('start_time')
                ->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($shifts);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get shift assignments for a date range
     */
    public function getAssignments(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $assignments = DB::table('shift_assignments')
                ->join('outlet_users', 'shift_assignments.user_id', '=', 'outlet_users.id')
                ->join('shifts', 'shift_assignments.shift_id', '=', 'shifts.id')
                ->whereBetween('shift_assignments.date', [$startDate, $endDate])
                ->select(
                    'shift_assignments.*',
                    'outlet_users.name as user_name',
                    'shifts.name as shift_name',
                    'shifts.code as shift_code',
                    'shifts.start_time',
                    'shifts.end_time',
                    'shifts.color as shift_color'
                )
                ->orderBy('shift_assignments.date')
                ->orderBy('shifts.start_time')
                ->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($assignments);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get calendar view (assignments + leaves)
     */
    public function getCalendar(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            // Get shift assignments
            $assignments = DB::table('shift_assignments')
                ->join('outlet_users', 'shift_assignments.user_id', '=', 'outlet_users.id')
                ->join('shifts', 'shift_assignments.shift_id', '=', 'shifts.id')
                ->whereBetween('shift_assignments.date', [$startDate, $endDate])
                ->select(
                    'shift_assignments.id',
                    'shift_assignments.date',
                    'shift_assignments.user_id',
                    'outlet_users.name as user_name',
                    'shift_assignments.shift_id',
                    'shifts.name as shift_name',
                    'shifts.code as shift_code',
                    'shifts.color as shift_color',
                    'shift_assignments.status'
                )
                ->get();
            
            // Get leave requests
            $leaves = DB::table('leave_requests')
                ->join('outlet_users', 'leave_requests.user_id', '=', 'outlet_users.id')
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(function($q) use ($startDate, $endDate) {
                              $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                          });
                })
                ->whereIn('status', ['pending', 'approved'])
                ->select(
                    'leave_requests.*',
                    'outlet_users.name as user_name'
                )
                ->get();
            
            // Expand leaves to individual dates
            $leavesByDate = [];
            foreach ($leaves as $leave) {
                $start = Carbon::parse($leave->start_date);
                $end = Carbon::parse($leave->end_date);
                
                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $dateStr = $date->toDateString();
                    if (!isset($leavesByDate[$dateStr])) {
                        $leavesByDate[$dateStr] = [];
                    }
                    $leavesByDate[$dateStr][] = [
                        'user_id' => $leave->user_id,
                        'user_name' => $leave->user_name,
                        'leave_type' => $leave->leave_type,
                        'status' => $leave->status,
                        'reason' => $leave->reason
                    ];
                }
            }
            
            // Get day offs (create table if not exists)
            $this->ensureDayOffsTable($outlet->schema_name);
            
            $dayOffs = DB::table('day_offs')
                ->join('outlet_users', 'day_offs.user_id', '=', 'outlet_users.id')
                ->whereBetween('day_offs.date', [$startDate, $endDate])
                ->select(
                    'day_offs.id',
                    'day_offs.user_id',
                    'day_offs.date',
                    'outlet_users.name as user_name',
                    'day_offs.notes'
                )
                ->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'assignments' => $assignments,
                'leaves' => $leavesByDate,
                'day_offs' => $dayOffs
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create shift assignment
     */
    public function assignShift(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'user_id' => 'required|integer',
            'shift_id' => 'required|integer',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            // Check if assignment already exists
            $exists = DB::table('shift_assignments')
                ->where('user_id', $request->user_id)
                ->where('date', $request->date)
                ->exists();
            
            if ($exists) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Shift already assigned for this date'], 422);
            }
            
            $id = DB::table('shift_assignments')->insertGetId([
                'user_id' => $request->user_id,
                'shift_id' => $request->shift_id,
                'date' => $request->date,
                'status' => 'scheduled',
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $assignment = DB::table('shift_assignments')->where('id', $id)->first();
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Shift assigned successfully',
                'data' => $assignment
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk assign shifts
     */
    public function bulkAssign(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'assignments' => 'required|array',
            'assignments.*.user_id' => 'required|integer',
            'assignments.*.shift_id' => 'required|integer',
            'assignments.*.date' => 'required|date',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $created = 0;
            foreach ($request->assignments as $assignment) {
                $exists = DB::table('shift_assignments')
                    ->where('user_id', $assignment['user_id'])
                    ->where('date', $assignment['date'])
                    ->exists();
                
                if (!$exists) {
                    DB::table('shift_assignments')->insert([
                        'user_id' => $assignment['user_id'],
                        'shift_id' => $assignment['shift_id'],
                        'date' => $assignment['date'],
                        'status' => 'scheduled',
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => "Created {$created} shift assignments",
                'count' => $created
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update shift assignment
     */
    public function updateAssignment(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'shift_id' => 'nullable|integer',
            'status' => 'nullable|in:scheduled,completed,absent,swapped',
            'notes' => 'nullable|string',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $data = array_filter([
                'shift_id' => $request->shift_id,
                'status' => $request->status,
                'notes' => $request->notes,
                'updated_at' => now(),
            ], function($value) {
                return $value !== null;
            });
            
            DB::table('shift_assignments')->where('id', $id)->update($data);
            
            $assignment = DB::table('shift_assignments')->where('id', $id)->first();
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Shift assignment updated successfully',
                'data' => $assignment
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete shift assignment
     */
    public function deleteAssignment($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            DB::table('shift_assignments')->where('id', $id)->delete();
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Shift assignment deleted successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get day off schedule for all employees in a date range
     */
    public function getDayOffSchedule(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            // Get all active employees
            $employees = DB::table('outlet_users')
                ->join('user_roles', 'outlet_users.id', '=', 'user_roles.user_id')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('outlet_users.is_active', true)
                ->where('roles.name', '!=', 'owner')
                ->whereNull('outlet_users.deleted_at')
                ->select(
                    'outlet_users.id',
                    'outlet_users.name',
                    'roles.display_name as role'
                )
                ->orderBy('roles.display_name')
                ->orderBy('outlet_users.name')
                ->get();
            
            $dayOffSchedule = [];
            
            // For each employee, find their day offs (days with no shift assignment)
            foreach ($employees as $employee) {
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);
                $dayOffs = [];
                
                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $dateStr = $date->toDateString();
                    
                    // Check if employee has shift assignment on this date
                    $hasAssignment = DB::table('shift_assignments')
                        ->where('user_id', $employee->id)
                        ->where('date', $dateStr)
                        ->exists();
                    
                    // Check if employee has approved leave on this date
                    $hasLeave = DB::table('leave_requests')
                        ->where('user_id', $employee->id)
                        ->where('status', 'approved')
                        ->where('start_date', '<=', $dateStr)
                        ->where('end_date', '>=', $dateStr)
                        ->exists();
                    
                    if (!$hasAssignment && !$hasLeave) {
                        $dayOffs[] = [
                            'date' => $dateStr,
                            'day_name' => $date->format('l'), // Monday, Tuesday, etc.
                            'day_name_id' => $this->getDayNameIndonesian($date->dayOfWeek)
                        ];
                    }
                }
                
                if (!empty($dayOffs)) {
                    $dayOffSchedule[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'role' => $employee->role,
                        'day_offs' => $dayOffs,
                        'total_day_offs' => count($dayOffs)
                    ];
                }
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'schedule' => $dayOffSchedule
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Helper function to get Indonesian day name
     */
    private function getDayNameIndonesian($dayOfWeek)
    {
        $days = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu'
        ];
        
        return $days[$dayOfWeek] ?? '';
    }

    /**
     * Auto-generate shift schedule with weekly day off consideration
     * Strategy: Each employee gets 1 day off per week, but flexible to ensure coverage
     */
    public function autoSchedule(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'overwrite' => 'boolean'
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            // Get payroll settings
            $settings = DB::table('payroll_settings')->first();
            $minStaffPerRole = $settings->min_staff_per_role ?? 1;
            
            // Get all active shifts (ordered by start_time)
            $shifts = DB::table('shifts')
                ->where('is_active', true)
                ->orderBy('start_time')
                ->get();
            
            if ($shifts->isEmpty()) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'No active shifts found'], 422);
            }
            
            // Get all active employees with their roles
            $employees = DB::table('outlet_users')
                ->join('user_roles', 'outlet_users.id', '=', 'user_roles.user_id')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('outlet_users.is_active', true)
                ->where('roles.name', '!=', 'owner')
                ->whereNull('outlet_users.deleted_at')
                ->select(
                    'outlet_users.id',
                    'outlet_users.name',
                    'roles.id as role_id',
                    'roles.name as role_name',
                    'roles.display_name as role_display'
                )
                ->get();
            
            if ($employees->isEmpty()) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'No active employees found'], 422);
            }
            
            // Group employees by role
            $employeesByRole = $employees->groupBy('role_name');
            
            // Get approved leave requests in date range
            $leaves = DB::table('leave_requests')
                ->where('status', 'approved')
                ->where(function($query) use ($request) {
                    $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                          ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                          ->orWhere(function($q) use ($request) {
                              $q->where('start_date', '<=', $request->start_date)
                                ->where('end_date', '>=', $request->end_date);
                          });
                })
                ->get();
            
            // Build leave map: user_id => [dates]
            $leaveMap = [];
            foreach ($leaves as $leave) {
                $start = Carbon::parse($leave->start_date);
                $end = Carbon::parse($leave->end_date);
                
                if (!isset($leaveMap[$leave->user_id])) {
                    $leaveMap[$leave->user_id] = [];
                }
                
                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $leaveMap[$leave->user_id][] = $date->toDateString();
                }
            }
            
            // Delete existing assignments if overwrite is true
            if ($request->overwrite) {
                DB::table('shift_assignments')
                    ->whereBetween('date', [$request->start_date, $request->end_date])
                    ->delete();
            }
            
            // Calculate date range
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            
            // Track last shift assigned per employee (for rotation)
            $employeeLastShift = [];
            
            // Track weekly day off per employee
            $employeeWeeklyDayOff = []; // [user_id => [week_number => date_string]]
            
            $created = 0;
            $skipped = 0;
            
            // Generate schedule: Day → Shift → Role → Employee
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateStr = $date->toDateString();
                $dayOfWeek = $date->dayOfWeek; // 0=Sunday, 6=Saturday
                $weekNumber = floor($date->diffInDays($startDate) / 7);
                
                // For EACH shift (Morning, Night)
                foreach ($shifts as $shift) {
                    // For EACH role, assign employees
                    foreach ($employeesByRole as $roleName => $roleEmployees) {
                        $assignedCount = 0;
                        
                        // Sort employees: prioritize those who haven't worked this shift recently
                        $sortedEmployees = $roleEmployees->sortBy(function($emp) use ($employeeLastShift, $shift) {
                            // Prioritize employees who worked different shift last time
                            $lastShift = $employeeLastShift[$emp->id] ?? null;
                            if ($lastShift == $shift->id) {
                                return 1; // Lower priority
                            }
                            return 0; // Higher priority
                        })->values();
                        
                        // Try to assign at least min_staff_per_role employees
                        foreach ($sortedEmployees as $employee) {
                            // Check if on approved leave
                            if (isset($leaveMap[$employee->id]) && in_array($dateStr, $leaveMap[$employee->id])) {
                                continue;
                            }
                            
                            // Check if already assigned to ANY shift today (max 1 shift per day)
                            $alreadyAssignedToday = DB::table('shift_assignments')
                                ->where('user_id', $employee->id)
                                ->where('date', $dateStr)
                                ->exists();
                            
                            if ($alreadyAssignedToday) {
                                continue;
                            }
                            
                            // Check if this employee should get weekly day off
                            $shouldGetDayOff = false;
                            
                            // Count work days this week
                            $weekStart = $startDate->copy()->addDays($weekNumber * 7);
                            $workDaysThisWeek = 0;
                            
                            for ($d = $weekStart->copy(); $d->lt($date); $d->addDay()) {
                                $worked = DB::table('shift_assignments')
                                    ->where('user_id', $employee->id)
                                    ->where('date', $d->toDateString())
                                    ->exists();
                                if ($worked) $workDaysThisWeek++;
                            }
                            
                            // Check if already has day off this week
                            $hasDayOffThisWeek = isset($employeeWeeklyDayOff[$employee->id][$weekNumber]);
                            
                            // Give day off if worked 6 days and no day off yet this week
                            if (!$hasDayOffThisWeek && $workDaysThisWeek >= 6) {
                                // Check if we have enough other employees to cover this role
                                $availableOthers = 0;
                                foreach ($roleEmployees as $other) {
                                    if ($other->id == $employee->id) continue;
                                    if (isset($leaveMap[$other->id]) && in_array($dateStr, $leaveMap[$other->id])) continue;
                                    
                                    $otherAssigned = DB::table('shift_assignments')
                                        ->where('user_id', $other->id)
                                        ->where('date', $dateStr)
                                        ->exists();
                                    
                                    if (!$otherAssigned) $availableOthers++;
                                }
                                
                                // Only give day off if we have enough coverage
                                if ($availableOthers >= $minStaffPerRole) {
                                    $shouldGetDayOff = true;
                                    
                                    // Mark day off
                                    if (!isset($employeeWeeklyDayOff[$employee->id])) {
                                        $employeeWeeklyDayOff[$employee->id] = [];
                                    }
                                    $employeeWeeklyDayOff[$employee->id][$weekNumber] = $dateStr;
                                }
                            }
                            
                            if ($shouldGetDayOff) {
                                continue; // Skip this employee for today
                            }
                            
                            // Assign employee to this shift
                            DB::table('shift_assignments')->insert([
                                'user_id' => $employee->id,
                                'shift_id' => $shift->id,
                                'date' => $dateStr,
                                'status' => 'scheduled',
                                'created_by' => Auth::id(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            
                            // Track last shift for rotation
                            $employeeLastShift[$employee->id] = $shift->id;
                            
                            $assignedCount++;
                            $created++;
                            
                            // Stop when we have enough for this role in this shift
                            if ($assignedCount >= $minStaffPerRole) {
                                break;
                            }
                        }
                        
                        // If we couldn't assign enough, log warning
                        if ($assignedCount < $minStaffPerRole) {
                            $skipped++;
                        }
                    }
                }
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Schedule generated successfully',
                'created' => $created,
                'skipped' => $skipped,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Ensure day_offs table exists
     */
    private function ensureDayOffsTable($schemaName)
    {
        try {
            DB::statement("SET search_path TO {$schemaName}, public");
            
            // Check if table exists
            $exists = DB::select("SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = '{$schemaName}' 
                AND table_name = 'day_offs'
            )");
            
            if (!$exists[0]->exists) {
                DB::statement("
                    CREATE TABLE {$schemaName}.day_offs (
                        id SERIAL PRIMARY KEY,
                        user_id INTEGER NOT NULL,
                        date DATE NOT NULL,
                        notes TEXT,
                        created_by INTEGER,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE(user_id, date)
                    )
                ");
            }
        } catch (\Exception $e) {
            \Log::error("Error ensuring day_offs table: " . $e->getMessage());
        }
    }
    
    /**
     * Create day off
     */
    public function createDayOff(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'user_id' => 'required|integer',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $this->ensureDayOffsTable($outlet->schema_name);
            
            // Check if already exists
            $exists = DB::table('day_offs')
                ->where('user_id', $request->user_id)
                ->where('date', $request->date)
                ->exists();
            
            if ($exists) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Day off already exists for this user on this date'], 422);
            }
            
            $id = DB::table('day_offs')->insertGetId([
                'user_id' => $request->user_id,
                'date' => $request->date,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Day off created successfully',
                'id' => $id
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Delete day off
     */
    public function deleteDayOff($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            DB::table('day_offs')->where('id', $id)->delete();
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Day off deleted successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Copy day schedule to next N days
     */
    public function copyDaySchedule(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'source_date' => 'required|date',
            'days' => 'required|integer|min:1|max:30',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $this->ensureDayOffsTable($outlet->schema_name);
            
            $sourceDate = $request->source_date;
            $days = $request->days;
            
            // Get source assignments
            $sourceAssignments = DB::table('shift_assignments')
                ->where('date', $sourceDate)
                ->get();
            
            // Get source day offs
            $sourceDayOffs = DB::table('day_offs')
                ->where('date', $sourceDate)
                ->get();
            
            $created = 0;
            
            // Copy to next N days
            for ($i = 1; $i <= $days; $i++) {
                $targetDate = Carbon::parse($sourceDate)->addDays($i)->toDateString();
                
                // Delete existing assignments for target date
                DB::table('shift_assignments')->where('date', $targetDate)->delete();
                DB::table('day_offs')->where('date', $targetDate)->delete();
                
                // Copy assignments
                foreach ($sourceAssignments as $assignment) {
                    DB::table('shift_assignments')->insert([
                        'user_id' => $assignment->user_id,
                        'shift_id' => $assignment->shift_id,
                        'date' => $targetDate,
                        'status' => 'scheduled',
                        'notes' => $assignment->notes,
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
                
                // Copy day offs
                foreach ($sourceDayOffs as $dayOff) {
                    DB::table('day_offs')->insert([
                        'user_id' => $dayOff->user_id,
                        'date' => $targetDate,
                        'notes' => $dayOff->notes,
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Schedule copied successfully',
                'created' => $created,
                'days' => $days
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
