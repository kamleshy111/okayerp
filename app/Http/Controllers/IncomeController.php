<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Services\AccountingService;
use Inertia\Inertia;

class IncomeController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $incomes = Income::with('category')
            ->where('user_id', $userId)
            ->get()
            ->map(function ($income) {
                return [
                    'id' => $income->id,
                    'income_category_id' => $income->income_category_id,
                    'category_name' => $income->category->name ?? '---',
                    'received_from' => $income->received_from ?? '---',
                    'amount' => $income->amount,
                    'date' => $income->date,
                    'reference_no' => $income->reference_no ?? '---',
                    'description' => $income->description ?? ''
                ];
            });

        $categories = IncomeCategory::where('user_id', $userId)
            ->where('status', 'active')
            ->get(['id', 'name']);

        return Inertia::render('Income/Income', [
            'incomes' => $incomes,
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'income_category_id' => 'required|exists:income_categories,id',
            'received_from' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'reference_no' => 'nullable|string|max:255'
        ]);

        $lastClosedDate = Auth::user()->last_closed_date;
        if ($lastClosedDate && $request->date <= $lastClosedDate) {
            return response()->json(['message' => 'Cannot create transactions on or before the last closed date (' . $lastClosedDate . ').'], 403);
        }

        // Security check: Make sure category belongs to this user
        IncomeCategory::where('user_id', Auth::id())->findOrFail($request->income_category_id);

        $income = Income::create([
            'user_id' => Auth::id(),
            'income_category_id' => $request->income_category_id,
            'received_from' => $request->received_from,
            'amount' => $request->amount,
            'date' => $request->date,
            'description' => $request->description,
            'reference_no' => $request->reference_no
        ]);

        $accountingService = new AccountingService(Auth::id());
        $accountingService->postIncome($income);

        return response()->json(['message' => 'Income added successfully!']);
    }

    public function update(Request $request, $id)
    {
        $income = Income::where('user_id', Auth::id())->findOrFail($id);

        $lastClosedDate = Auth::user()->last_closed_date;
        if ($lastClosedDate) {
            if ($income->date <= $lastClosedDate) {
                return response()->json(['message' => 'Cannot update transactions from a closed financial year.'], 403);
            }
            if ($request->date <= $lastClosedDate) {
                return response()->json(['message' => 'Cannot change transaction date to a closed financial year.'], 403);
            }
        }

        $request->validate([
            'income_category_id' => 'required|exists:income_categories,id',
            'received_from' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'reference_no' => 'nullable|string|max:255'
        ]);

        // Security check: Make sure category belongs to this user
        IncomeCategory::where('user_id', Auth::id())->findOrFail($request->income_category_id);

        $income->update([
            'income_category_id' => $request->income_category_id,
            'received_from' => $request->received_from,
            'amount' => $request->amount,
            'date' => $request->date,
            'description' => $request->description,
            'reference_no' => $request->reference_no
        ]);

        $accountingService = new AccountingService(Auth::id());
        $accountingService->postIncome($income);

        return response()->json(['message' => 'Income updated successfully.']);
    }

    public function destroy($id)
    {
        $income = Income::where('user_id', Auth::id())->findOrFail($id);

        $lastClosedDate = Auth::user()->last_closed_date;
        if ($lastClosedDate && $income->date <= $lastClosedDate) {
            return response()->json(['message' => 'Cannot delete transactions from a closed financial year.'], 403);
        }
        $income->delete();

        $accountingService = new AccountingService(Auth::id());
        $accountingService->clearEntries('Income', $id);

        return response()->json(['message' => 'Income deleted successfully.'], 200);
    }
}
