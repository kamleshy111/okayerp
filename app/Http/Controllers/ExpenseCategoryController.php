<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ExpenseCategory;
use Inertia\Inertia;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $categories = ExpenseCategory::where('user_id', $userId)->get();

        return Inertia::render('ExpenseCategory/Category', [
            'categories' => $categories
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string'
        ]);

        ExpenseCategory::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status ?? 'active'
        ]);

        return response()->json(['message' => 'Expense category added successfully!']);
    }


    public function update(Request $request, $id)
    {
        $category = ExpenseCategory::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string'
        ]);

        $category->update([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status ?? 'active'
        ]);

        return response()->json(['message' => 'Expense category updated successfully.']);
    }

    public function destroy($id)
    {
        $category = ExpenseCategory::where('user_id', Auth::id())->findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Expense category deleted successfully.'], 200);
    }
}
