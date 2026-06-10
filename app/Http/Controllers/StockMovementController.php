<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use App\Models\StockMovement;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        
        $query = StockMovement::where('user_id', $userId)
            ->with('product')
            ->orderBy('id', 'desc');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        $movements = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'product_name' => $item->product->name ?? 'Deleted Product',
                'sku' => $item->product->sku ?? 'N/A',
                'quantity' => $item->quantity,
                'type' => $item->type,
                'reference_type' => $item->reference_type,
                'reason' => $item->reason,
                'remarks' => $item->remarks ?? '',
                'date' => $item->created_at->format('d-m-Y H:i'),
            ];
        });

        $products = Product::where('user_id', $userId)->get();

        return Inertia::render('StockAdjustment/Index', [
            'movements' => $movements,
            'products' => $products,
            'selectedProductId' => $request->input('product_id', ''),
        ]);
    }

    public function create()
    {
        $userId = Auth::id();
        $products = Product::where('user_id', $userId)->get();

        return Inertia::render('StockAdjustment/Create', [
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'type' => 'required|in:Addition,Deduction',
            'reason' => 'required|in:Physical Count Correction,Damaged Goods,Expired Stock,Theft/Loss,Other',
            'remarks' => 'nullable|string|max:1000',
        ], [
            'product_id.required' => 'Please select a product.',
            'quantity.required' => 'Adjustment quantity is required.',
            'quantity.min' => 'Quantity must be at least 1.',
            'type.required' => 'Adjustment type is required.',
            'reason.required' => 'Reason is required.',
        ]);

        // Validate product belongs to the store owner
        $product = Product::where('user_id', $userId)->find($request->input('product_id'));
        if (!$product) {
            return response()->json(['message' => 'Unauthorized or invalid product.'], 403);
        }

        if ($request->input('type') === 'Deduction' && $product->stock_quantity < $request->input('quantity')) {
            return response()->json([
                'message' => "Insufficient stock. Only {$product->stock_quantity} units are currently available."
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Update product stock level
            if ($request->input('type') === 'Addition') {
                $product->stock_quantity += $request->input('quantity');
            } else {
                $product->stock_quantity -= $request->input('quantity');
            }
            $product->save();

            // Create stock movement record
            StockMovement::create([
                'user_id' => $userId,
                'product_id' => $product->id,
                'quantity' => $request->input('quantity'),
                'type' => $request->input('type'),
                'reference_type' => 'Manual',
                'reference_id' => null,
                'reason' => $request->input('reason'),
                'remarks' => $request->input('remarks'),
            ]);

            DB::commit();

            return response()->json(['message' => 'Stock adjusted successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to adjust stock. Please try again.', 'error' => $e->getMessage()], 500);
        }
    }
}
