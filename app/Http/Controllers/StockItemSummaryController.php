<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockItemSummaryController extends Controller
{
    public function show(Request $request, $productId)
    {
        $userId = Auth::id();

        // Verify product belongs to this user
        $product = Product::with('category')
            ->where('user_id', $userId)
            ->where('id', $productId)
            ->firstOrFail();

        $user      = Auth::user();
        $storeName = $user->name ?? 'Store';

        // ── Financial Year boundaries (April–March) ──────────────────────
        $now         = Carbon::now();
        $fyStartYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyStart     = Carbon::create($fyStartYear,     4, 1)->startOfDay();
        $fyEnd       = Carbon::create($fyStartYear + 1, 3, 31)->endOfDay();
        $fyLabel     = '1-Apr-' . substr((string)$fyStartYear, -2);
        $fyYear      = $fyStartYear . '-' . substr((string)($fyStartYear + 1), -2);

        $driver = DB::connection()->getDriverName();
        $inwardsMonthRaw = $driver === 'sqlite' 
            ? "CAST(strftime('%m', COALESCE(purchases.purchase_date, purchases.created_at)) AS INTEGER) as month" 
            : 'MONTH(COALESCE(purchases.purchase_date, purchases.created_at)) as month';
        $inwardsYearRaw = $driver === 'sqlite' 
            ? "CAST(strftime('%Y', COALESCE(purchases.purchase_date, purchases.created_at)) AS INTEGER) as year" 
            : 'YEAR(COALESCE(purchases.purchase_date, purchases.created_at)) as year';

        $outwardsMonthRaw = $driver === 'sqlite' 
            ? "CAST(strftime('%m', sales.created_at) AS INTEGER) as month" 
            : 'MONTH(sales.created_at) as month';
        $outwardsYearRaw = $driver === 'sqlite' 
            ? "CAST(strftime('%Y', sales.created_at) AS INTEGER) as year" 
            : 'YEAR(sales.created_at) as year';

        // ── Monthly Inwards (accepted purchases within FY) ────────────────
        $inwardsRaw = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.user_id', $userId)
            ->where('purchases.accepted', 1)
            ->where('purchase_items.product_id', $productId)
            ->whereBetween(
                DB::raw('COALESCE(purchases.purchase_date, purchases.created_at)'),
                [$fyStart, $fyEnd]
            )
            ->select(
                DB::raw($inwardsMonthRaw),
                DB::raw($inwardsYearRaw),
                DB::raw('SUM(purchase_items.quantity) as total_qty'),
                DB::raw('SUM(COALESCE(purchase_items.base_price, purchase_items.price, 0) * purchase_items.quantity) as total_value')
            )
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn($r) => $r->year . '-' . $r->month);

        // ── Monthly Outwards (accepted sales within FY) ───────────────────
        $outwardsRaw = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId)
            ->where('sales.accepted', 1)
            ->where('sale_items.product_id', $productId)
            ->whereBetween('sales.created_at', [$fyStart, $fyEnd])
            ->select(
                DB::raw($outwardsMonthRaw),
                DB::raw($outwardsYearRaw),
                DB::raw('SUM(sale_items.quantity) as total_qty'),
                DB::raw('SUM(COALESCE(sale_items.base_price, sale_items.price, 0) * sale_items.quantity) as total_value')
            )
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn($r) => $r->year . '-' . $r->month);

        // ── Total inwards & outwards in FY (for opening balance calc) ─────
        $totalInFY = (int) DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.user_id', $userId)
            ->where('purchases.accepted', 1)
            ->where('purchase_items.product_id', $productId)
            ->whereBetween(
                DB::raw('COALESCE(purchases.purchase_date, purchases.created_at)'),
                [$fyStart, $fyEnd]
            )
            ->sum('purchase_items.quantity');

        $totalOutFY = (int) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId)
            ->where('sales.accepted', 1)
            ->where('sale_items.product_id', $productId)
            ->whereBetween('sales.created_at', [$fyStart, $fyEnd])
            ->sum('sale_items.quantity');

        // Opening balance = current stock − net FY movements
        $currentStock = (int)($product->stock_quantity ?? 0);
        $openingQty   = max(0, $currentStock - $totalInFY + $totalOutFY);
        $sellingPrice = (float)($product->price ?? 0);
        $openingValue = round($openingQty * $sellingPrice, 2);

        // ── Build month-by-month rows ─────────────────────────────────────
        $fyMonths = [
            ['month' => 4,  'year' => $fyStartYear,     'label' => 'April'],
            ['month' => 5,  'year' => $fyStartYear,     'label' => 'May'],
            ['month' => 6,  'year' => $fyStartYear,     'label' => 'June'],
            ['month' => 7,  'year' => $fyStartYear,     'label' => 'July'],
            ['month' => 8,  'year' => $fyStartYear,     'label' => 'August'],
            ['month' => 9,  'year' => $fyStartYear,     'label' => 'September'],
            ['month' => 10, 'year' => $fyStartYear,     'label' => 'October'],
            ['month' => 11, 'year' => $fyStartYear,     'label' => 'November'],
            ['month' => 12, 'year' => $fyStartYear,     'label' => 'December'],
            ['month' => 1,  'year' => $fyStartYear + 1, 'label' => 'January'],
            ['month' => 2,  'year' => $fyStartYear + 1, 'label' => 'February'],
            ['month' => 3,  'year' => $fyStartYear + 1, 'label' => 'March'],
        ];

        $rows        = [];
        $runningQty  = $openingQty;
        $grandInQty  = 0;
        $grandInVal  = 0.0;
        $grandOutQty = 0;
        $grandOutVal = 0.0;

        foreach ($fyMonths as $m) {
            $key    = $m['year'] . '-' . $m['month'];
            $inQty  = (int)(isset($inwardsRaw[$key])  ? $inwardsRaw[$key]->total_qty   : 0);
            $inVal  = (float)(isset($inwardsRaw[$key]) ? $inwardsRaw[$key]->total_value  : 0);
            $outQty = (int)(isset($outwardsRaw[$key])  ? $outwardsRaw[$key]->total_qty  : 0);
            $outVal = (float)(isset($outwardsRaw[$key]) ? $outwardsRaw[$key]->total_value : 0);

            $runningQty  += $inQty - $outQty;
            $closingQty   = $runningQty;
            $closingVal   = round($closingQty * $sellingPrice, 2);

            $grandInQty  += $inQty;
            $grandInVal  += $inVal;
            $grandOutQty += $outQty;
            $grandOutVal += $outVal;

            $rows[] = [
                'label'         => $m['label'],
                'month'         => $m['month'],
                'year'          => $m['year'],
                'in_qty'        => $inQty,
                'in_value'      => round($inVal, 2),
                'out_qty'       => $outQty,
                'out_value'     => round($outVal, 2),
                'closing_qty'   => $closingQty,
                'closing_value' => $closingVal,
                'is_current'    => ($m['month'] === $now->month && $m['year'] === $now->year),
            ];
        }

        $lastRow = end($rows);

        return Inertia::render('Reports/StockItemSummary', [
            'product'         => [
                'id'        => $product->id,
                'name'      => $product->name,
                'sku'       => $product->sku,
                'unit_type' => $product->unit_type ?? '',
                'price'     => $sellingPrice,
                'category'  => $product->category?->name ?? '—',
            ],
            'storeName'        => $storeName,
            'fyLabel'          => $fyLabel,
            'fyYear'           => $fyYear,
            'openingQty'       => $openingQty,
            'openingValue'     => $openingValue,
            'rows'             => $rows,
            'grandInQty'       => $grandInQty,
            'grandInVal'       => round($grandInVal, 2),
            'grandOutQty'      => $grandOutQty,
            'grandOutVal'      => round($grandOutVal, 2),
            'grandClosingQty'  => $lastRow ? $lastRow['closing_qty']   : $openingQty,
            'grandClosingVal'  => $lastRow ? $lastRow['closing_value']  : $openingValue,
        ]);
    }
}
