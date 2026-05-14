<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    private function authorizeOutlet($outletId)
    {
        $outlet = DB::table('outlets')->where('id', $outletId)->first();
        
        if (!$outlet) {
            abort(404, 'Outlet not found');
        }
        
        return $outlet;
    }

    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $query = DB::table('expenses')
                ->orderBy('expense_date', 'desc')
                ->orderBy('id', 'desc');
            
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('expense_date', [$request->start_date, $request->end_date]);
            }
            
            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }
            
            $expenses = $query->get();
            
            DB::statement("SET search_path TO public");
            
            return response()->json($expenses);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|string|max:100',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'payment_proof' => 'nullable|image|max:5120',
            'notes' => 'nullable|string',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            // Generate expense code
            $lastExpense = DB::table('expenses')
                ->whereYear('expense_date', date('Y', strtotime($request->expense_date)))
                ->orderBy('id', 'desc')
                ->first();
            
            $year = date('Y', strtotime($request->expense_date));
            $month = date('m', strtotime($request->expense_date));
            $sequence = $lastExpense ? (intval(substr($lastExpense->expense_code, -4)) + 1) : 1;
            $expenseCode = "EXP-{$year}{$month}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            
            // Handle payment proof upload
            $paymentProofUrl = null;
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs("outlets/{$outletId}/expenses", $filename, 'public');
                $paymentProofUrl = Storage::url($path);
            }
            
            DB::table('expenses')->insert([
                'expense_code' => $expenseCode,
                'expense_date' => $request->expense_date,
                'category' => $request->category,
                'description' => $request->description,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_proof_url' => $paymentProofUrl,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Expense created successfully',
                'expense_code' => $expenseCode
            ], 201);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $expense = DB::table('expenses')->where('id', $id)->first();
            
            if (!$expense) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Expense not found'], 404);
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json($expense);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|string|max:100',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'payment_proof' => 'nullable|image|max:5120',
            'notes' => 'nullable|string',
        ]);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $expense = DB::table('expenses')->where('id', $id)->first();
            
            if (!$expense) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Expense not found'], 404);
            }
            
            $paymentProofUrl = $expense->payment_proof_url;
            
            // Handle new payment proof upload
            if ($request->hasFile('payment_proof')) {
                // Delete old file
                if ($paymentProofUrl) {
                    $path = str_replace('/storage/', '', $paymentProofUrl);
                    Storage::disk('public')->delete($path);
                }
                
                $file = $request->file('payment_proof');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs("outlets/{$outletId}/expenses", $filename, 'public');
                $paymentProofUrl = Storage::url($path);
            }
            
            DB::table('expenses')->where('id', $id)->update([
                'expense_date' => $request->expense_date,
                'category' => $request->category,
                'description' => $request->description,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_proof_url' => $paymentProofUrl,
                'notes' => $request->notes,
                'updated_at' => now(),
            ]);
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Expense updated successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy($outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $expense = DB::table('expenses')->where('id', $id)->first();
            
            if (!$expense) {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Expense not found'], 404);
            }
            
            // Delete payment proof if exists
            if ($expense->payment_proof_url) {
                $path = str_replace('/storage/', '', $expense->payment_proof_url);
                Storage::disk('public')->delete($path);
            }
            
            DB::table('expenses')->where('id', $id)->delete();
            
            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Expense deleted successfully']);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getCategories($outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $categories = DB::table('expenses')
                ->select('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category');
            
            DB::statement("SET search_path TO public");
            
            return response()->json($categories);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
