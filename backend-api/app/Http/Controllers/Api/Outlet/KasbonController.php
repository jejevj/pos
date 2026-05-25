<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class KasbonController extends Controller
{
    use AuthorizesOutletAccess;


    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $query = DB::table('kasbon')
                ->join('outlet_users', 'kasbon.user_id', '=', 'outlet_users.id')
                ->leftJoin('outlet_users as approver', 'kasbon.approved_by', '=', 'approver.id')
                ->select(
                    'kasbon.*',
                    'outlet_users.name as user_name',
                    'approver.name as approved_by_name'
                )
                ->orderBy('kasbon.request_date', 'desc')
                ->orderBy('kasbon.id', 'desc');
            
            if ($request->has('status')) {
                $query->where('kasbon.status', $request->status);
            }
            
            if ($request->has('user_id')) {
                $query->where('kasbon.user_id', $request->user_id);
            }
            
            $kasbon = $query->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($kasbon);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, $outletId)
    {
        // Hanya karyawan aktif yang terdaftar di outlet yang bisa mengajukan kasbon untuk dirinya sendiri.
        // Superadmin / owner tidak bisa bypass — kasbon harus milik outlet_user yang nyata.
        $outlet = $this->authorizeOutletAsEmployee($outletId);

        $request->validate([
            'request_date' => 'required|date',
            'amount'       => 'required|numeric|min:0',
            'reason'       => 'nullable|string',
        ]);

        // Ambil outlet_user yang sedang login — sudah di-resolve oleh authorizeOutletAsEmployee.
        $outletUser = $this->currentOutletUser();

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            // Ambil info gaji berdasarkan outlet_user.id (bukan global user.id)
            $employee = DB::table('employee_info')
                ->where('user_id', $outletUser->id)
                ->first();

            if (!$employee) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Data karyawan tidak ditemukan. Pastikan profil karyawan sudah dilengkapi.'], 404);
            }

            $settings      = DB::table('kasbon_settings')->first();
            $maxPercentage = $settings->max_percentage ?? 50;
            $maxAllowed    = ($employee->basic_salary * $maxPercentage) / 100;

            if ($request->amount > $maxAllowed) {
                DB::statement("SET search_path TO public");
                return response()->json([
                    'message'     => "Jumlah kasbon melebihi batas maksimal ({$maxPercentage}% dari gaji pokok)",
                    'max_allowed' => $maxAllowed,
                ], 422);
            }

            $pendingKasbon = DB::table('kasbon')
                ->where('user_id', $outletUser->id)
                ->whereIn('status', ['pending', 'approved'])
                ->where('repayment_status', 'unpaid')
                ->sum('amount');

            if (($pendingKasbon + $request->amount) > $maxAllowed) {
                DB::statement("SET search_path TO public");
                return response()->json([
                    'message'         => 'Total kasbon belum lunas akan melebihi batas maksimal',
                    'current_unpaid'  => $pendingKasbon,
                    'max_allowed'     => $maxAllowed,
                ], 422);
            }

            DB::table('kasbon')->insert([
                'user_id'          => $outletUser->id,   // selalu milik user yang login
                'request_date'     => $request->request_date,
                'amount'           => $request->amount,
                'reason'           => $request->reason,
                'status'           => 'pending',
                'repayment_status' => 'unpaid',
                'created_by'       => Auth::id(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            DB::statement("SET search_path TO public");

            return response()->json(['message' => 'Pengajuan kasbon berhasil dibuat'], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function approve(Request $request, $outletId, $id)
    {
        // Superadmin & owner boleh approve. Outlet user harus punya permission approve_kasbon.
        $outlet = $this->authorizeOutlet($outletId, ['permission' => 'approve_kasbon']);

        $request->validate([
            'approval_proof' => 'required|string',   // base64 bukti persetujuan
        ]);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $kasbon = DB::table('kasbon')->where('id', $id)->first();

            if (!$kasbon) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Kasbon tidak ditemukan'], 404);
            }

            if ($kasbon->status !== 'pending') {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Kasbon sudah diproses sebelumnya'], 422);
            }

            // Pastikan approver bukan pemohon itu sendiri
            $outletUser = $this->currentOutletUser();
            if ($outletUser && $outletUser->id === $kasbon->user_id) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Anda tidak dapat menyetujui kasbon milik sendiri'], 403);
            }

            $approverId = $outletUser ? $outletUser->id : null;

            DB::table('kasbon')->where('id', $id)->update([
                'status'         => 'approved',
                'approved_by'    => $approverId,
                'approved_at'    => now(),
                'approval_proof' => $request->approval_proof,
                'updated_at'     => now(),
            ]);

            DB::statement("SET search_path TO public");

            return response()->json(['message' => 'Kasbon berhasil disetujui']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function reject(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId, ['permission' => 'approve_kasbon']);

        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $kasbon = DB::table('kasbon')->where('id', $id)->first();

            if (!$kasbon) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Kasbon tidak ditemukan'], 404);
            }

            if ($kasbon->status !== 'pending') {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Kasbon sudah diproses sebelumnya'], 422);
            }

            $outletUser = $this->currentOutletUser();
            if ($outletUser && $outletUser->id === $kasbon->user_id) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Anda tidak dapat menolak kasbon milik sendiri'], 403);
            }

            $reviewerId = $outletUser ? $outletUser->id : null;

            DB::table('kasbon')->where('id', $id)->update([
                'status'           => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'approved_by'      => $reviewerId,
                'approved_at'      => now(),
                'updated_at'       => now(),
            ]);

            DB::statement("SET search_path TO public");

            return response()->json(['message' => 'Kasbon berhasil ditolak']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function markAsPaid(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId, ['permission' => 'approve_kasbon']);

        $request->validate([
            'repayment_amount' => 'required|numeric|min:0',
            'repayment_date'   => 'required|date',
            'repayment_proof'  => 'required|string',   // base64 bukti pelunasan
            'notes'            => 'nullable|string',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $kasbon = DB::table('kasbon')->where('id', $id)->first();
            
            if (!$kasbon) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Kasbon not found'], 404);
            }
            
            if ($kasbon->status !== 'approved') {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Kasbon must be approved first'], 422);
            }
            
            DB::table('kasbon')->where('id', $id)->update([
                'repayment_status' => 'paid',
                'repayment_amount' => $request->repayment_amount,
                'repayment_date' => $request->repayment_date,
                'repayment_proof' => $request->repayment_proof,
                'notes' => $request->notes,
                'updated_at' => now(),
            ]);
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Kasbon marked as paid successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getSettings($outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $settings = DB::table('kasbon_settings')->first();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($settings);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateSettings(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'max_percentage' => 'required|numeric|min:0|max:100',
            'require_approval' => 'required|boolean',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            DB::table('kasbon_settings')->update([
                'max_percentage' => $request->max_percentage,
                'require_approval' => $request->require_approval,
                'updated_at' => now(),
            ]);
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Kasbon settings updated successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getUserSummary($outletId, $userId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            // Get employee info
            $employee = DB::table('employee_info')
                ->where('user_id', $userId)
                ->first();
            
            if (!$employee) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Employee not found'], 404);
            }
            
            // Get kasbon settings
            $settings = DB::table('kasbon_settings')->first();
            $maxPercentage = $settings->max_percentage ?? 50;
            $maxAllowed = ($employee->basic_salary * $maxPercentage) / 100;
            
            // Get unpaid kasbon
            $unpaidKasbon = DB::table('kasbon')
                ->where('user_id', $userId)
                ->whereIn('status', ['pending', 'approved'])
                ->where('repayment_status', 'unpaid')
                ->sum('amount');
            
            // Get total kasbon history
            $totalKasbon = DB::table('kasbon')
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->sum('amount');
            
            // Get paid kasbon
            $paidKasbon = DB::table('kasbon')
                ->where('user_id', $userId)
                ->where('repayment_status', 'paid')
                ->sum('repayment_amount');
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'basic_salary' => $employee->basic_salary,
                'max_percentage' => $maxPercentage,
                'max_allowed' => $maxAllowed,
                'unpaid_kasbon' => $unpaidKasbon,
                'available_kasbon' => max(0, $maxAllowed - $unpaidKasbon),
                'total_kasbon' => $totalKasbon,
                'paid_kasbon' => $paidKasbon,
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
