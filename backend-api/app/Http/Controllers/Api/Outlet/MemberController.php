<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MemberController extends Controller
{
    use AuthorizesOutletAccess;


    /**
     * Get all members
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $query = Member::query();
            
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nama', 'ILIKE', "%{$search}%")
                      ->orWhere('phone', 'ILIKE', "%{$search}%")
                      ->orWhere('email', 'ILIKE', "%{$search}%")
                      ->orWhere('card_number', 'ILIKE', "%{$search}%");
                });
            }
            
            if ($request->has('tier')) {
                $query->where('tier', $request->tier);
            }
            
            $members = $query->orderBy('created_at', 'desc')->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($members);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Search members (for POS)
     */
    public function search(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $searchQuery = $request->input('query');
            
            $members = Member::where('is_active', true)
                ->where(function($q) use ($searchQuery) {
                    $q->where('nama', 'ILIKE', "%{$searchQuery}%")
                      ->orWhere('phone', 'ILIKE', "%{$searchQuery}%")
                      ->orWhere('card_number', 'ILIKE', "%{$searchQuery}%");
                })
                ->limit(10)
                ->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($members);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create new member
     */
    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'password' => 'nullable|string|min:6|confirmed',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
            'alamat' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $memberData = [
                'card_number' => $request->card_number ?? Member::generateCardNumber(),
                'nama' => $request->nama,
                'phone' => $request->phone,
                'email' => $request->email,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'alamat' => $request->alamat,
                'points' => 0,
                'tier' => 'Silver',
                'joined_at' => now(),
                'is_active' => true,
            ];

            if ($request->filled('password')) {
                $memberData['password'] = $request->password;
            }

            $member = Member::create($memberData);
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Member created successfully',
                'data' => $member
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get member detail
     */
    public function show($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $member = Member::findOrFail($id);
            
            // Load recent transactions
            $transactions = DB::table('point_transactions')
                ->where('member_id', $member->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            $member->recent_transactions = $transactions;
            
            DB::statement("SET search_path TO public");
            
            return response()->json($member);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update member
     */
    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'password' => 'nullable|string|min:6|confirmed',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
            'alamat' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $member = Member::findOrFail($id);
            
            $updateData = [
                'nama' => $request->nama,
                'phone' => $request->phone,
                'email' => $request->email,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'alamat' => $request->alamat,
                'is_active' => $request->is_active ?? $member->is_active,
            ];

            if ($request->filled('password')) {
                $updateData['password'] = $request->password;
            }

            $member->update($updateData);
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Member updated successfully',
                'data' => $member
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete member
     */
    public function destroy($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $member = Member::findOrFail($id);
            $member->delete();
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Member deleted successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Adjust member points manually
     */
    public function adjustPoints(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:add,subtract',
            'amount' => 'required|integer|min:1',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $member = Member::findOrFail($id);
            
            if ($request->type === 'add') {
                $member->addPoints($request->amount, $request->description);
            } else {
                $member->redeemPoints($request->amount, $request->description);
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Points adjusted successfully',
                'data' => $member
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get member point transactions
     */
    public function transactions($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $member = Member::findOrFail($id);
            
            $transactions = DB::table('point_transactions')
                ->where('member_id', $member->id)
                ->orderBy('created_at', 'desc')
                ->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($transactions);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
