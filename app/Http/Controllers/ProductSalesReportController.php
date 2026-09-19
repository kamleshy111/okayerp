<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Category;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ProductSalesReportController extends Controller
{
    /**
     * Display the Product-wise Sales Report.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $user = Auth::user();
        $reportData = $this->getReportData($request, $userId);

        $categoriesQuery = Category::query();
        $customersQuery = Customer::query();

        if ($user && $user->role !== 'admin') {
            $categoriesQuery->where('user_id', $userId);
            $customersQuery->where('user_id', $userId);
        }

        $categories = $categoriesQuery->select('id', 'name')->orderBy('name')->get();
        $customers = $customersQuery->select('id', 'name', 'phone')->orderBy('name')->get();

        return Inertia::render('Reports/ProductSales', [
            'products'   => $reportData['products'],
            'categories' => $categories,
            'customers'  => $customers,
            'filters'    => $reportData['filters'],
            'summary'    => $reportData['summary'],
            'store'      => [
                'name'    => $user->name ?? 'Store',
                'email'   => $user->email ?? '',
                'phone'   => $user->phone ?? '',
                'address' => $user->address ?? '',
                'gstin'   => $user->gstin ?? '',
            ]
        ]);
    }

    /**
     * Download PDF version of the report.
     */
    public function downloadPdf(Request $request)
    {
        $userId = Auth::id();
        $user = Auth::user();
        $reportData = $this->getReportData($request, $userId);

        $products = $reportData['products'];
        $summary = $reportData['summary'];
        $filters = $reportData['filters'];

        $store = [
            'name'    => $user->name ?? 'OkayERP Store',
            'email'   => $user->email ?? '',
            'phone'   => $user->phone ?? '',
            'address' => $user->address ?? '',
            'gstin'   => $user->gstin ?? '',
            'state'   => $user->state ?? '',
        ];

        $pdf = Pdf::loadView('product_sales_report_pdf', compact('products', 'summary', 'filters', 'store'))
            ->setPaper('a4', 'landscape');

        $fromLabel = !empty($filters['from_date']) ? $filters['from_date'] : 'All';
        $toLabel = !empty($filters['to_date']) ? $filters['to_date'] : 'Latest';
        $fileName = 'Product_Sales_Report_' . $fromLabel . '_to_' . $toLabel . '.pdf';

        return $pdf->stream($fileName);
    }

    /**
     * Export the report data to CSV / Excel format.
     */
    public function exportCsv(Request $request)
    {
        $userId = Auth::id();
        $reportData = $this->getReportData($request, $userId);
        $products = $reportData['products'];
        $filters = $reportData['filters'];

        $fromLabel = !empty($filters['from_date']) ? $filters['from_date'] : 'All';
        $toLabel = !empty($filters['to_date']) ? $filters['to_date'] : 'Latest';
        $fileName = 'Product_Sales_' . $fromLabel . '_to_' . $toLabel . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                '#',
                'Product Name',
                'SKU / Code',
                'Unit',
                'Gross Qty Sold',
                'Return Qty',
                'Net Qty Sold',
                'Purchase Rate (₹)',
                'Purchase Amount (₹)',
                'Sale Rate (₹)',
                'Sale Amount (₹)',
                'Profit (₹)'
            ]);

            foreach ($products as $idx => $row) {
                fputcsv($file, [
                    $idx + 1,
                    $row['product_name'],
                    $row['product_sku'] ?: '-',
                    $row['unit_type'] ?: 'Pcs',
                    $row['gross_quantity'],
                    $row['return_quantity'],
                    $row['net_quantity'],
                    number_format($row['purchase_rate'] ?? 0, 2, '.', ''),
                    number_format($row['total_cost'] ?? 0, 2, '.', ''),
                    number_format($row['avg_rate'], 2, '.', ''),
                    number_format($row['net_amount'], 2, '.', ''),
                    number_format($row['total_profit'] ?? 0, 2, '.', '')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get detailed transaction list for a specific product within the filter period.
     */
    public function details(Request $request, $productId)
    {
        $userId = Auth::id();
        $user = Auth::user();
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $productQuery = Product::query();
        if ($user && $user->role !== 'admin') {
            $productQuery->where('user_id', $userId);
        }
        $product = $productQuery->find($productId);

        // Fetch purchase rate for this product
        $purchaseRateQuery = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('purchase_items.product_id', $productId);

        if ($user && $user->role !== 'admin') {
            $purchaseRateQuery->where('suppliers.user_id', $userId);
        }

        $purchaseRate = (float) $purchaseRateQuery->value(
            DB::raw('ROUND(SUM(purchase_items.quantity * purchase_items.price) / NULLIF(SUM(purchase_items.quantity), 0), 2)')
        );

        $salesItemsQuery = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sale_items.product_id', $productId);

        if ($user && $user->role !== 'admin') {
            $salesItemsQuery->where(function ($q) use ($userId) {
                $q->where('sales.user_id', $userId)
                  ->orWhere('customers.user_id', $userId);
            });
        }

        if (!empty($fromDate)) {
            $salesItemsQuery->where(function ($q) use ($fromDate) {
                $q->where(function ($sub) use ($fromDate) {
                    $sub->whereNotNull('sales.sale_date')
                        ->whereDate('sales.sale_date', '>=', $fromDate);
                })->orWhere(function ($sub) use ($fromDate) {
                    $sub->whereNull('sales.sale_date')
                        ->whereDate('sales.created_at', '>=', $fromDate);
                });
            });
        }

        if (!empty($toDate)) {
            $salesItemsQuery->where(function ($q) use ($toDate) {
                $q->where(function ($sub) use ($toDate) {
                    $sub->whereNotNull('sales.sale_date')
                        ->whereDate('sales.sale_date', '<=', $toDate);
                })->orWhere(function ($sub) use ($toDate) {
                    $sub->whereNull('sales.sale_date')
                        ->whereDate('sales.created_at', '<=', $toDate);
                });
            });
        }

        $salesItems = $salesItemsQuery->select(
                'sale_items.id as item_id',
                'sales.id as sale_id',
                'sales.invoice_no',
                'sales.sale_date',
                'sales.created_at',
                'customers.id as customer_id',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'sale_items.quantity',
                'sale_items.price',
                'sale_items.base_price',
                'sale_items.sgst',
                'sale_items.cgst',
                'sale_items.unit_type'
            )
            ->orderBy('sales.sale_date', 'desc')
            ->orderBy('sales.id', 'desc')
            ->get()
            ->map(function ($row) use ($purchaseRate) {
                $qty = (float)$row->quantity;
                $price = (float)$row->price;
                $taxable = (float)$row->base_price > 0 ? (float)$row->base_price : ($qty * $price);
                $gstRate = (float)$row->sgst + (float)$row->cgst;
                $gstAmt = $taxable * ($gstRate / 100);
                $total = $taxable + $gstAmt;
                $itemCost = $qty * $purchaseRate;
                $itemProfit = $total - $itemCost;

                return [
                    'item_id'        => $row->item_id,
                    'sale_id'        => $row->sale_id,
                    'invoice_no'     => $row->invoice_no ?: ('#' . $row->sale_id),
                    'date'           => $row->sale_date ?: substr($row->created_at, 0, 10),
                    'customer_id'    => $row->customer_id,
                    'customer_name'  => $row->customer_name ?: 'Cash Customer',
                    'customer_phone' => $row->customer_phone ?: '',
                    'quantity'       => $qty,
                    'unit_type'      => $row->unit_type ?: 'Pcs',
                    'purchase_rate'  => $purchaseRate,
                    'price'          => $price,
                    'profit'         => round($itemProfit, 2),
                    'taxable_amount' => round($taxable, 2),
                    'gst_rate'       => $gstRate,
                    'gst_amount'     => round($gstAmt, 2),
                    'total_amount'   => round($total, 2),
                ];
            });

        return response()->json([
            'product' => [
                'id'        => $product ? $product->id : $productId,
                'name'      => $product ? $product->name : 'Item #' . $productId,
                'sku'       => $product ? $product->sku : '',
                'unit_type' => $product ? $product->unit_type : 'Pcs',
            ],
            'transactions' => $salesItems
        ]);
    }

    /**
     * Core aggregation logic for the Product-wise Sales Report.
     */
    private function getReportData(Request $request, $userId)
    {
        $user = Auth::user();
        $fromDate = $request->input('from_date', '');
        $toDate = $request->input('to_date', '');
        $categoryId = $request->input('category_id');
        $customerId = $request->input('customer_id');
        $search = trim($request->input('search', ''));

        // 1. Fetch Sales Items with Joins
        $itemsQuery = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id');

        // Store user scope
        if ($user && $user->role !== 'admin') {
            $itemsQuery->where(function ($q) use ($userId) {
                $q->where('sales.user_id', $userId)
                  ->orWhere('customers.user_id', $userId)
                  ->orWhere('products.user_id', $userId);
            });
        }

        // Date Filter (From Date)
        if (!empty($fromDate)) {
            $itemsQuery->where(function ($q) use ($fromDate) {
                $q->where(function ($sub) use ($fromDate) {
                    $sub->whereNotNull('sales.sale_date')
                        ->whereDate('sales.sale_date', '>=', $fromDate);
                })->orWhere(function ($sub) use ($fromDate) {
                    $sub->whereNull('sales.sale_date')
                        ->whereDate('sales.created_at', '>=', $fromDate);
                });
            });
        }

        // Date Filter (To Date)
        if (!empty($toDate)) {
            $itemsQuery->where(function ($q) use ($toDate) {
                $q->where(function ($sub) use ($toDate) {
                    $sub->whereNotNull('sales.sale_date')
                        ->whereDate('sales.sale_date', '<=', $toDate);
                })->orWhere(function ($sub) use ($toDate) {
                    $sub->whereNull('sales.sale_date')
                        ->whereDate('sales.created_at', '<=', $toDate);
                });
            });
        }

        // Category Filter
        if (!empty($categoryId)) {
            $itemsQuery->where('products.category_id', $categoryId);
        }

        // Customer Filter
        if (!empty($customerId)) {
            $itemsQuery->where('sales.customer_id', $customerId);
        }

        // Search Filter
        if (!empty($search)) {
            $itemsQuery->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }

        $rawItems = $itemsQuery->select(
            'sale_items.id as item_id',
            'sale_items.sale_id',
            'sale_items.product_id',
            'sale_items.quantity',
            'sale_items.price',
            'sale_items.base_price',
            'sale_items.sgst',
            'sale_items.cgst',
            'sale_items.unit_type as item_unit_type',
            DB::raw("COALESCE(products.name, CONCAT('Product #', sale_items.product_id)) as product_name"),
            'products.sku as product_sku',
            'products.unit_type as product_unit_type',
            'categories.id as category_id',
            'categories.name as category_name',
            'sales.invoice_no',
            'sales.sale_date',
            'sales.discount as sale_discount',
            'sales.total_amount as sale_total_amount',
            'sales.grand_total as sale_grand_total',
            'sales.created_at as sale_created_at',
            'customers.id as customer_id',
            'customers.name as customer_name'
        )
        ->orderBy('products.name', 'asc')
        ->orderBy('sales.sale_date', 'desc')
        ->get();

        // 2. Fetch Sales Returns within the period
        $returnsQuery = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id');

        if ($user && $user->role !== 'admin') {
            $returnsQuery->where('sale_returns.user_id', $userId);
        }

        if (!empty($fromDate)) {
            $returnsQuery->whereDate('sale_returns.return_date', '>=', $fromDate);
        }
        if (!empty($toDate)) {
            $returnsQuery->whereDate('sale_returns.return_date', '<=', $toDate);
        }
        if (!empty($customerId)) {
            $returnsQuery->where('sale_returns.customer_id', $customerId);
        }

        $returns = $returnsQuery->select(
            'sale_return_items.product_id',
            DB::raw('SUM(sale_return_items.quantity) as return_qty'),
            DB::raw('SUM(sale_return_items.quantity * sale_return_items.price) as return_amount')
        )
        ->groupBy('sale_return_items.product_id')
        ->get()
        ->keyBy('product_id');

        // 3. Group and aggregate per product
        $grouped = $rawItems->groupBy('product_id');
        $productsList = [];

        // Fetch purchase rates/costs for all sold products
        $productIds = $grouped->keys()->filter()->all();
        $purchaseCosts = [];
        if (!empty($productIds)) {
            $purchaseCostsQuery = DB::table('purchase_items')
                ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
                ->whereIn('purchase_items.product_id', $productIds);

            if ($user && $user->role !== 'admin') {
                $purchaseCostsQuery->where('suppliers.user_id', $userId);
            }

            $purchaseCosts = $purchaseCostsQuery->select(
                'purchase_items.product_id',
                DB::raw('ROUND(SUM(purchase_items.quantity * purchase_items.price) / NULLIF(SUM(purchase_items.quantity), 0), 2) as avg_purchase_rate')
            )
            ->groupBy('purchase_items.product_id')
            ->get()
            ->keyBy('product_id');
        }

        foreach ($grouped as $productId => $items) {
            $first = $items->first();
            $grossQty = 0.0;
            $totalTaxable = 0.0;
            $totalGst = 0.0;
            $transactions = [];

            $purchaseRate = isset($purchaseCosts[$productId]) ? (float)$purchaseCosts[$productId]->avg_purchase_rate : 0.0;

            foreach ($items as $item) {
                $qty = (float)$item->quantity;
                $rawPrice = (float)$item->price;
                $rawTaxable = (float)$item->base_price > 0 ? (float)$item->base_price : ($qty * $rawPrice);

                // Proportionate discount deduction (डिस्काउंट हटाना)
                $saleTotal = (float)$item->sale_total_amount > 0 ? (float)$item->sale_total_amount : $rawTaxable;
                $saleDiscount = (float)($item->sale_discount ?? 0);
                $itemDiscount = ($saleTotal > 0 && $saleDiscount > 0)
                    ? round(($rawTaxable / $saleTotal) * $saleDiscount, 2)
                    : 0.0;

                $taxable = max(0, $rawTaxable - $itemDiscount);
                $effectivePrice = $qty > 0 ? round($taxable / $qty, 2) : $rawPrice;

                $gstRate = (float)$item->sgst + (float)$item->cgst;
                $gstAmt = $taxable * ($gstRate / 100);
                $totalAmt = $taxable + $gstAmt;
                $itemCost = $qty * $purchaseRate;
                $itemProfit = $totalAmt - $itemCost;

                $grossQty += $qty;
                $totalTaxable += $taxable;
                $totalGst += $gstAmt;

                $transactions[] = [
                    'item_id'         => $item->item_id,
                    'sale_id'         => $item->sale_id,
                    'invoice_no'      => $item->invoice_no ?: ('#' . $item->sale_id),
                    'date'            => $item->sale_date ?: substr($item->sale_created_at, 0, 10),
                    'customer_id'     => $item->customer_id,
                    'customer_name'   => $item->customer_name ?: 'Cash Customer',
                    'quantity'        => $qty,
                    'unit_type'       => $item->item_unit_type ?: ($item->product_unit_type ?: 'Pcs'),
                    'purchase_rate'   => $purchaseRate,
                    'purchase_amount' => round($itemCost, 2),
                    'price'           => $effectivePrice,
                    'original_price'  => $rawPrice,
                    'discount_amount' => $itemDiscount,
                    'profit'          => round($itemProfit, 2),
                    'taxable_amount'  => round($taxable, 2),
                    'gst_rate'        => $gstRate,
                    'gst_amount'      => round($gstAmt, 2),
                    'total_amount'    => round($totalAmt, 2),
                ];
            }

            // Return adjustments
            $returnQty = isset($returns[$productId]) ? (float)$returns[$productId]->return_qty : 0.0;
            $returnAmt = isset($returns[$productId]) ? (float)$returns[$productId]->return_amount : 0.0;

            $netQty = max(0, $grossQty - $returnQty);
            $netTotalAmount = max(0, ($totalTaxable + $totalGst) - $returnAmt);
            $avgRate = $netQty > 0 ? round($netTotalAmount / $netQty, 2) : ($grossQty > 0 ? round($totalTaxable / $grossQty, 2) : 0.0);

            $totalCost = round($netQty * $purchaseRate, 2);
            $totalProfit = round($netTotalAmount - $totalCost, 2);
            $unitMargin = $grossQty > 0 ? round($avgRate - $purchaseRate, 2) : 0.0;
            $profitMarginPct = $totalCost > 0 ? round(($totalProfit / $totalCost) * 100, 1) : 0.0;

            $productsList[] = [
                'product_id'        => $productId,
                'product_name'      => $first->product_name,
                'product_sku'       => $first->product_sku,
                'category_id'       => $first->category_id,
                'category_name'     => $first->category_name ?: 'General',
                'unit_type'         => $first->product_unit_type ?: ($first->item_unit_type ?: 'Pcs'),
                'gross_quantity'    => round($grossQty, 2),
                'return_quantity'   => round($returnQty, 2),
                'net_quantity'      => round($netQty, 2),
                'purchase_rate'     => $purchaseRate,
                'avg_rate'          => $avgRate,
                'unit_margin'       => $unitMargin,
                'total_cost'        => $totalCost,
                'total_profit'      => $totalProfit,
                'profit_margin_pct' => $profitMarginPct,
                'taxable_amount'    => round($totalTaxable, 2),
                'gst_amount'        => round($totalGst, 2),
                'net_amount'        => round($netTotalAmount, 2),
                'transactions'      => $transactions,
            ];
        }

        // Grand totals for KPIs
        $grandGrossQty = collect($productsList)->sum('gross_quantity');
        $grandReturnQty = collect($productsList)->sum('return_quantity');
        $grandNetQty = collect($productsList)->sum('net_quantity');
        $grandTaxable = collect($productsList)->sum('taxable_amount');
        $grandGst = collect($productsList)->sum('gst_amount');
        $grandNetTotal = collect($productsList)->sum('net_amount');
        $grandTotalCost = collect($productsList)->sum('total_cost');
        $grandTotalProfit = collect($productsList)->sum('total_profit');
        $grandProfitMarginPct = $grandTotalCost > 0 ? round(($grandTotalProfit / $grandTotalCost) * 100, 1) : 0.0;

        // Calculate % share of total net revenue for each product
        foreach ($productsList as &$p) {
            $p['share_percentage'] = $grandNetTotal > 0
                ? round(($p['net_amount'] / $grandNetTotal) * 100, 1)
                : 0.0;
        }
        unset($p);

        // Sort descending by net sales amount by default (Tally high-value item first)
        usort($productsList, function ($a, $b) {
            return $b['net_amount'] <=> $a['net_amount'];
        });

        return [
            'products' => $productsList,
            'filters'  => [
                'from_date'   => $fromDate,
                'to_date'     => $toDate,
                'category_id' => $categoryId ? (int)$categoryId : '',
                'customer_id' => $customerId ? (int)$customerId : '',
                'search'      => $search,
            ],
            'summary'  => [
                'total_products'    => count($productsList),
                'total_gross_qty'   => round($grandGrossQty, 2),
                'total_return_qty'  => round($grandReturnQty, 2),
                'total_net_qty'     => round($grandNetQty, 2),
                'total_taxable'     => round($grandTaxable, 2),
                'total_gst'         => round($grandGst, 2),
                'total_net_revenue' => round($grandNetTotal, 2),
                'total_cost'        => round($grandTotalCost, 2),
                'total_profit'      => round($grandTotalProfit, 2),
                'profit_margin_pct' => $grandProfitMarginPct,
            ]
        ];
    }
}
