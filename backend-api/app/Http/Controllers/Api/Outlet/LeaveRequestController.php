<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaveRequestController extends Controller
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
     * Get leave requests
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $query = DB::table('leave_requests')
                ->join('outlet_users', 'leave_requests.user_id', '=', 'outlet_users.id')
                ->leftJoin('outlet_users as reviewer', 'leave_requests.reviewed_by', '=', 'reviewer.id')
                ->select(
                    'leave_requests.*',
                    'outlet_users.name as user_name',
                    'outlet_users.email',
                    'reviewer.name as reviewer_name'
                );
            
            if ($request->has('user_id')) {
                $query->where('leave_requests.user_id', $request->user_id);
            }
            
            if ($request->has('status')) {
                $query->where('leave_requests.status', $request->status);
            }
            
            if ($request->has('leave_type')) {
                $query->where('leave_requests.leave_type', $request->leave_type);
            }
            
            $leaveRequests = $query->orderBy('leave_requests.created_at', 'desc')->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($leaveRequests);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create leave request
     */
    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'user_id' => 'required|integer',
            'leave_type' => 'required|in:annual,sick,unpaid,emergency',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'attachment' => 'nullable|string',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $totalDays = $startDate->diffInDays($endDate) + 1;
            
            // Check leave balance for annual leave
            if ($request->leave_type === 'annual') {
                $balance = DB::table('leave_balances')
                    ->where('user_id', $request->user_id)
                    ->where('year', $startDate->year)
                    ->where('leave_type', 'annual')
                    ->first();
                
                if (!$balance || $balance->remaining_days < $totalDays) {
                    DB::statement("SET search_path TO public");
                    return response()->json(['message' => 'Insufficient leave balance'], 422);
                }
            }
            
            $id = DB::table('leave_requests')->insertGetId([
                'user_id' => $request->user_id,
                'leave_type' => $request->leave_type,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_days' => $totalDays,
                'reason' => $request->reason,
                'attachment' => $request->attachment,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $leaveRequest = DB::table('leave_requests')->where('id', $id)->first();
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Leave request created successfully',
                'data' => $leaveRequest
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update leave request status (approve/reject)
     */
    public function updateStatus(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'status' => 'required|in:approved,rejected,cancelled',
            'reviewed_by' => 'required|integer',
            'review_notes' => 'nullable|string',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $leaveRequest = DB::table('leave_requests')->where('id', $id)->first();
            
            if (!$leaveRequest) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Leave request not found'], 404);
            }
            
            if ($leaveRequest->status !== 'pending') {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Leave request already processed'], 422);
            }
            
            DB::table('leave_requests')
                ->where('id', $id)
                ->update([
                    'status' => $request->status,
                    'reviewed_by' => $request->reviewed_by,
                    'reviewed_at' => now(),
                    'review_notes' => $request->review_notes,
                    'updated_at' => now(),
                ]);
            
            // Update leave balance if approved
            if ($request->status === 'approved' && $leaveRequest->leave_type === 'annual') {
                $year = Carbon::parse($leaveRequest->start_date)->year;
                
                DB::table('leave_balances')
                    ->where('user_id', $leaveRequest->user_id)
                    ->where('year', $year)
                    ->where('leave_type', 'annual')
                    ->update([
                        'used_days' => DB::raw("used_days + {$leaveRequest->total_days}"),
                        'remaining_days' => DB::raw("remaining_days - {$leaveRequest->total_days}"),
                        'updated_at' => now(),
                    ]);
            }
            
            $updated = DB::table('leave_requests')->where('id', $id)->first();
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Leave request updated successfully',
                'data' => $updated
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get leave balance
     */
    public function getBalance(Request $request, $outletId, $userId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $year = $request->input('year', Carbon::now()->year);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $balances = DB::table('leave_balances')
                ->where('user_id', $userId)
                ->where('year', $year)
                ->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($balances);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Initialize leave balance for a user
     */
    public function initializeBalance(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'user_id' => 'required|integer',
            'year' => 'required|integer',
            'annual_days' => 'integer|min:0|max:30',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $annualDays = $request->input('annual_days', 12);
            
            // Check if balance already exists
            $exists = DB::table('leave_balances')
                ->where('user_id', $request->user_id)
                ->where('year', $request->year)
                ->where('leave_type', 'annual')
                ->exists();
            
            if ($exists) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Leave balance already initialized'], 422);
            }
            
            DB::table('leave_balances')->insert([
                'user_id' => $request->user_id,
                'year' => $request->year,
                'leave_type' => 'annual',
                'total_days' => $annualDays,
                'used_days' => 0,
                'remaining_days' => $annualDays,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Leave balance initialized successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
