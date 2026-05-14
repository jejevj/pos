<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    use AuthorizesOutletAccess;


    /**
     * Get all employees with their info
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $employees = DB::table('outlet_users')
                ->leftJoin('employee_info', 'outlet_users.id', '=', 'employee_info.user_id')
                ->leftJoin('user_roles', 'outlet_users.id', '=', 'user_roles.user_id')
                ->leftJoin('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('outlet_users.is_active', true)
                ->whereNull('outlet_users.deleted_at')
                ->select(
                    'outlet_users.id',
                    'outlet_users.name',
                    'outlet_users.email',
                    'outlet_users.phone',
                    'roles.name as role_name',
                    'roles.display_name as role_display',
                    'employee_info.employee_code',
                    'employee_info.join_date',
                    'employee_info.employment_type',
                    'employee_info.basic_salary',
                    'employee_info.hourly_rate',
                    'employee_info.overtime_rate',
                    'employee_info.bank_name',
                    'employee_info.bank_account',
                    'employee_info.bank_account_name',
                    'employee_info.emergency_contact_name',
                    'employee_info.emergency_contact_phone',
                    'employee_info.address'
                )
                ->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($employees);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get employee details
     */
    public function show($outletId, $userId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $employee = DB::table('outlet_users')
                ->leftJoin('employee_info', 'outlet_users.id', '=', 'employee_info.user_id')
                ->leftJoin('user_roles', 'outlet_users.id', '=', 'user_roles.user_id')
                ->leftJoin('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('outlet_users.id', $userId)
                ->select(
                    'outlet_users.*',
                    'roles.name as role_name',
                    'roles.display_name as role_display',
                    'employee_info.*'
                )
                ->first();
            
            if (!$employee) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Employee not found'], 404);
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json($employee);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update employee info
     */
    public function updateInfo(Request $request, $outletId, $userId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'employee_code' => 'nullable|string|max:50',
            'join_date' => 'nullable|date',
            'employment_type' => 'nullable|in:full_time,part_time,contract',
            'basic_salary' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'overtime_rate' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string'
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            // Check if employee info exists
            $exists = DB::table('employee_info')->where('user_id', $userId)->exists();
            
            $data = array_filter([
                'employee_code' => $request->employee_code,
                'join_date' => $request->join_date,
                'employment_type' => $request->employment_type,
                'basic_salary' => $request->basic_salary,
                'hourly_rate' => $request->hourly_rate,
                'overtime_rate' => $request->overtime_rate,
                'bank_name' => $request->bank_name,
                'bank_account' => $request->bank_account,
                'bank_account_name' => $request->bank_account_name,
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_contact_phone' => $request->emergency_contact_phone,
                'address' => $request->address,
                'updated_at' => now(),
            ], function($value) {
                return $value !== null;
            });
            
            if ($exists) {
                DB::table('employee_info')
                    ->where('user_id', $userId)
                    ->update($data);
            } else {
                $data['user_id'] = $userId;
                $data['created_at'] = now();
                DB::table('employee_info')->insert($data);
            }
            
            $employee = DB::table('employee_info')->where('user_id', $userId)->first();
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Employee info updated successfully',
                'data' => $employee
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get payroll settings
     */
    public function getPayrollSettings($outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            // Check if settings table exists, if not create it
            $tableExists = DB::select("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = current_schema()
                    AND table_name = 'payroll_settings'
                )
            ");
            
            if (!$tableExists[0]->exists) {
                DB::statement("
                    CREATE TABLE payroll_settings (
                        id SERIAL PRIMARY KEY,
                        work_days_per_month INTEGER DEFAULT 22,
                        work_hours_per_day DECIMAL(5,2) DEFAULT 8,
                        overtime_multiplier DECIMAL(5,2) DEFAULT 1.5,
                        late_tolerance_minutes INTEGER DEFAULT 15,
                        annual_leave_days INTEGER DEFAULT 12,
                        sick_leave_days INTEGER DEFAULT 12,
                        tax_percentage DECIMAL(5,2) DEFAULT 0,
                        min_staff_per_role INTEGER DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                
                // Insert default settings
                DB::table('payroll_settings')->insert([
                    'work_days_per_month' => 22,
                    'work_hours_per_day' => 8,
                    'overtime_multiplier' => 1.5,
                    'late_tolerance_minutes' => 15,
                    'annual_leave_days' => 12,
                    'sick_leave_days' => 12,
                    'tax_percentage' => 0,
                    'min_staff_per_role' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            $settings = DB::table('payroll_settings')->first();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($settings);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update payroll settings
     */
    public function updatePayrollSettings(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'work_days_per_month' => 'nullable|integer|min:1|max:31',
            'work_hours_per_day' => 'nullable|numeric|min:1|max:24',
            'overtime_multiplier' => 'nullable|numeric|min:1|max:5',
            'late_tolerance_minutes' => 'nullable|integer|min:0|max:60',
            'annual_leave_days' => 'nullable|integer|min:0|max:30',
            'sick_leave_days' => 'nullable|integer|min:0|max:30',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'attendance_location_lat' => 'nullable|numeric',
            'attendance_location_lng' => 'nullable|numeric',
            'attendance_radius' => 'nullable|integer|min:10|max:1000',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $data = array_filter([
                'work_days_per_month' => $request->work_days_per_month,
                'work_hours_per_day' => $request->work_hours_per_day,
                'overtime_multiplier' => $request->overtime_multiplier,
                'late_tolerance_minutes' => $request->late_tolerance_minutes,
                'annual_leave_days' => $request->annual_leave_days,
                'sick_leave_days' => $request->sick_leave_days,
                'tax_percentage' => $request->tax_percentage,
                'attendance_location_lat' => $request->attendance_location_lat,
                'attendance_location_lng' => $request->attendance_location_lng,
                'attendance_radius' => $request->attendance_radius,
                'updated_at' => now(),
            ], function($value) {
                return $value !== null;
            });
            
            DB::table('payroll_settings')->update($data);
            
            $settings = DB::table('payroll_settings')->first();
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Payroll settings updated successfully',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
