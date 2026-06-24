<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\AccountingService;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $expenses = Expense::with('category')
            ->where('user_id', $userId)
            ->get()
            ->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'expense_category_id' => $expense->expense_category_id,
                    'category_name' => $expense->category->name ?? 'N/A',
                    'paid_to' => $expense->paid_to ?? 'N/A',
                    'amount' => $expense->amount,
                    'date' => $expense->date,
                    'reference_no' => $expense->reference_no ?? 'N/A',
                    'description' => $expense->description ?? ''
                ];
            });

        $categories = ExpenseCategory::where('user_id', $userId)
            ->where('status', 'active')
            ->get(['id', 'name']);

        return Inertia::render('Expense/Expense', [
            'expenses' => $expenses,
            'categories' => $categories
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'paid_to' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'reference_no' => 'nullable|string|max:255'
        ]);

        // Security check: Make sure category belongs to this user
        ExpenseCategory::where('user_id', Auth::id())->findOrFail($request->expense_category_id);

        $expense = Expense::create([
            'user_id' => Auth::id(),
            'expense_category_id' => $request->expense_category_id,
            'paid_to' => $request->paid_to,
            'amount' => $request->amount,
            'date' => $request->date,
            'description' => $request->description,
            'reference_no' => $request->reference_no
        ]);

        $accountingService = new AccountingService(Auth::id());
        $accountingService->postExpense($expense);

        return response()->json(['message' => 'Expense added successfully!']);
    }


    public function update(Request $request, $id)
    {
        $expense = Expense::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'paid_to' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'reference_no' => 'nullable|string|max:255'
        ]);

        // Security check: Make sure category belongs to this user
        ExpenseCategory::where('user_id', Auth::id())->findOrFail($request->expense_category_id);

        $expense->update([
            'expense_category_id' => $request->expense_category_id,
            'paid_to' => $request->paid_to,
            'amount' => $request->amount,
            'date' => $request->date,
            'description' => $request->description,
            'reference_no' => $request->reference_no
        ]);

        $accountingService = new AccountingService(Auth::id());
        $accountingService->postExpense($expense);

        return response()->json(['message' => 'Expense updated successfully.']);
    }

    public function destroy($id)
    {
        $expense = Expense::where('user_id', Auth::id())->findOrFail($id);
        $expense->delete();

        $accountingService = new AccountingService(Auth::id());
        $accountingService->clearEntries('Expense', $id);

        return response()->json(['message' => 'Expense deleted successfully.'], 200);
    }
}
