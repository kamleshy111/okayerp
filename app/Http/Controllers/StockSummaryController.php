<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class StockSummaryController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();

        // Fetch all products with category info
        $products = Product::select(
            'products.id',
            'products.name',
            'products.sku',
            'products.price',
            'products.stock_quantity',
            'products.unit_type',
            'categories.name as category_name'
        )
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.user_id', $userId)
            ->orderBy('products.name')
            ->get();

        // Get average purchase price per product from accepted purchases
        $avgPurchasePrices = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.user_id', $userId)
            ->where('purchases.accepted', 1)
            ->select(
                'purchase_items.product_id',
                DB::raw('ROUND(AVG(purchase_items.base_price), 2) as avg_purchase_price')
            )
            ->groupBy('purchase_items.product_id')
            ->pluck('avg_purchase_price', 'product_id');

        // Get categories for filter
        $categories = Category::where('user_id', $userId)->select('id', 'name')->orderBy('name')->get();

        // Build product summary rows
        $summaryRows = $products->map(function ($product) use ($avgPurchasePrices) {
            $avgCost = (float) ($avgPurchasePrices[$product->id] ?? 0);
            $sellingPrice = (float) ($product->price ?? 0);
            $stock = (int) ($product->stock_quantity ?? 0);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category_name' => $product->category_name ?? '—',
                'stock_quantity' => $stock,
                'unit_type' => $product->unit_type ?? '',
                'avg_purchase_price' => $avgCost,
                'selling_price' => $sellingPrice,
                'cost_valuation' => round($stock * $avgCost, 2),
                'retail_valuation' => round($stock * $sellingPrice, 2),
            ];
        });

        // Summary totals
        $totalProducts = $summaryRows->count();
        $totalQtyInStock = $summaryRows->sum('stock_quantity');
        $totalCostValue = $summaryRows->sum('cost_valuation');
        $totalRetailValue = $summaryRows->sum('retail_valuation');
        $outOfStockCount = $summaryRows->where('stock_quantity', 0)->count();
        $lowStockCount = $summaryRows->filter(fn($r) => $r['stock_quantity'] > 0 && $r['stock_quantity'] < 10)->count();

        return Inertia::render('Reports/StockSummary', [
            'products' => $summaryRows->values(),
            'categories' => $categories,
            'totalProducts' => $totalProducts,
            'totalQtyInStock' => $totalQtyInStock,
            'totalCostValue' => round($totalCostValue, 2),
            'totalRetailValue' => round($totalRetailValue, 2),
            'outOfStockCount' => $outOfStockCount,
            'lowStockCount' => $lowStockCount,
        ]);
    }
}
