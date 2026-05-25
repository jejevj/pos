<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayrollController extends Controller
{
    use AuthorizesOutletAccess;


    /**
     * Get payrolls
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $query = DB::table('payrolls')
                ->join('outlet_users', 'payrolls.user_id', '=', 'outlet_users.id')
                ->select('payrolls.*', 'outlet_users.name as user_name', 'outlet_users.email');
            
            if ($request->has('user_id')) {
                $query->where('payrolls.user_id', $request->user_id);
            }
            
            if ($request->has('month')) {
                $query->where('payrolls.period_month', $request->month);
            }
            
            if ($request->has('year')) {
                $query->where('payrolls.period_year', $request->year);
            }
            
            if ($request->has('status')) {
                $query->where('payrolls.status', $request->status);
            }
            
            $payrolls = $query->orderBy('payrolls.period_year', 'desc')
                ->orderBy('payrolls.period_month', 'desc')
                ->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($payrolls);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate payroll for a period
     */
    public function generate(Request $request, $outletId)
    {
        // Hanya owner/superadmin atau user dengan permission manage_payroll yang bisa generate
        $outlet = $this->authorizeOutlet($outletId, ['permission' => 'manage_payroll']);
        
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $month = $request->month;
            $year = $request->year;
            
            // Get users to generate payroll for
            $usersQuery = DB::table('outlet_users')
                ->join('employee_info', 'outlet_users.id', '=', 'employee_info.user_id')
                ->where('outlet_users.is_active', true)
                ->where('employee_info.is_active', true)
                ->select(
                    'outlet_users.id as user_id',
                    'outlet_users.name',
                    'employee_info.basic_salary',
                    'employee_info.overtime_rate',
                    'employee_info.employee_code',
                    'employee_info.employment_type'
                );
            
            if ($request->has('user_ids') && !empty($request->user_ids)) {
                $usersQuery->whereIn('outlet_users.id', $request->user_ids);
            }
            
            $users = $usersQuery->get();
            
            if ($users->isEmpty()) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'No active employees found'], 422);
            }
            
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();
            $workDays = $startDate->diffInDaysFiltered(function (Carbon $date) {
                return $date->isWeekday();
            }, $endDate) + 1;
            
            $generated = 0;
            
            foreach ($users as $user) {
                // Check if payroll already exists
                $exists = DB::table('payrolls')
                    ->where('user_id', $user->user_id)
                    ->where('period_month', $month)
                    ->where('period_year', $year)
                    ->exists();
                
                if ($exists) continue;
                
                // Get attendance data
                // Overtime hanya dihitung jika sudah approved — pending/rejected tidak masuk payroll
                $attendance = DB::table('attendances')
                    ->where('user_id', $user->user_id)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->select(
                        DB::raw("COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days"),
                        DB::raw("COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days"),
                        DB::raw("COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_days"),
                        DB::raw("COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days"),
                        DB::raw("SUM(CASE WHEN overtime_status = 'approved' THEN overtime_hours ELSE 0 END) as overtime_hours")
                    )
                    ->first();
                
                $presentDays = $attendance->present_days ?? 0;
                $absentDays = $attendance->absent_days ?? 0;
                $leaveDays = $attendance->leave_days ?? 0;
                $lateDays = $attendance->late_days ?? 0;
                $overtimeHours = $attendance->overtime_hours ?? 0;
                
                // Calculate salary
                $basicSalary = $user->basic_salary ?? 0;
                $overtimePay = $overtimeHours * ($user->overtime_rate ?? 0);
                
                // Deductions for absent days
                $dailyRate = $basicSalary / $workDays;
                $deductions = $absentDays * $dailyRate;
                
                $grossSalary = $basicSalary + $overtimePay;
                $netSalary = $grossSalary - $deductions;
                
                DB::table('payrolls')->insert([
                    'user_id' => $user->user_id,
                    'period_month' => $month,
                    'period_year' => $year,
                    'basic_salary' => $basicSalary,
                    'overtime_pay' => $overtimePay,
                    'allowances' => 0,
                    'bonuses' => 0,
                    'deductions' => $deductions,
                    'gross_salary' => $grossSalary,
                    'net_salary' => $netSalary,
                    'work_days' => $workDays,
                    'present_days' => $presentDays,
                    'absent_days' => $absentDays,
                    'leave_days' => $leaveDays,
                    'late_days' => $lateDays,
                    'overtime_hours' => $overtimeHours,
                    'status' => 'draft',
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $generated++;
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => "Generated {$generated} payroll records",
                'count' => $generated
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update payroll
     */
    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'allowances' => 'nullable|numeric|min:0',
            'bonuses' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $payroll = DB::table('payrolls')->where('id', $id)->first();
            
            if (!$payroll) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Payroll not found'], 404);
            }
            
            if ($payroll->status === 'paid') {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Cannot update paid payroll'], 422);
            }
            
            $allowances = $request->input('allowances', $payroll->allowances);
            $bonuses = $request->input('bonuses', $payroll->bonuses);
            $deductions = $request->input('deductions', $payroll->deductions);
            
            $grossSalary = $payroll->basic_salary + $payroll->overtime_pay + $allowances + $bonuses;
            $netSalary = $grossSalary - $deductions;
            
            DB::table('payrolls')
                ->where('id', $id)
                ->update([
                    'allowances' => $allowances,
                    'bonuses' => $bonuses,
                    'deductions' => $deductions,
                    'gross_salary' => $grossSalary,
                    'net_salary' => $netSalary,
                    'notes' => $request->notes,
                    'updated_at' => now(),
                ]);
            
            $updated = DB::table('payrolls')->where('id', $id)->first();
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Payroll updated successfully',
                'data' => $updated
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve payroll
     */
    public function approve(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId, ['permission' => 'manage_payroll']);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $payroll = DB::table('payrolls')->where('id', $id)->first();
            
            if (!$payroll) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Payroll not found'], 404);
            }
            
            if ($payroll->status !== 'draft') {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Payroll already processed'], 422);
            }
            
            DB::table('payrolls')
                ->where('id', $id)
                ->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);
            
            $updated = DB::table('payrolls')->where('id', $id)->first();
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Payroll approved successfully',
                'data' => $updated
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark payroll as paid
     */
    public function markAsPaid(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId, ['permission' => 'manage_payroll']);
        
        $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $payroll = DB::table('payrolls')->where('id', $id)->first();
            
            if (!$payroll) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Payroll not found'], 404);
            }
            
            if ($payroll->status !== 'approved') {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Payroll must be approved first'], 422);
            }
            
            DB::table('payrolls')
                ->where('id', $id)
                ->update([
                    'status' => 'paid',
                    'payment_date' => $request->payment_date,
                    'payment_method' => $request->payment_method,
                    'updated_at' => now(),
                ]);
            
            $updated = DB::table('payrolls')->where('id', $id)->first();

            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Payroll marked as paid',
                'data' => $updated
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get payroll details
     */
    public function show($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $payroll = DB::table('payrolls')
                ->join('outlet_users', 'payrolls.user_id', '=', 'outlet_users.id')
                ->leftJoin('employee_info', 'outlet_users.id', '=', 'employee_info.user_id')
                ->where('payrolls.id', $id)
                ->select('payrolls.*', 'outlet_users.name as user_name', 'outlet_users.email', 'employee_info.employee_code', 'employee_info.bank_name', 'employee_info.bank_account')
                ->first();
            
            if (!$payroll) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Payroll not found'], 404);
            }
            
            $details = DB::table('payroll_details')
                ->where('payroll_id', $id)
                ->get();
            
            $payroll->details = $details;
            
            DB::statement("SET search_path TO public");
            
            return response()->json($payroll);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
