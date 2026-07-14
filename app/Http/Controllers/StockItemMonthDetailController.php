<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockItemMonthDetailController extends Controller
{
    public function show(Request $request, $productId, $year, $month)
    {
        $userId = Auth::id();

        // Verify product belongs to user
        $product = Product::with('category')
            ->where('user_id', $userId)
            ->where('id', $productId)
            ->firstOrFail();

        $user      = Auth::user();
        $storeName = $user->name ?? 'Store';

        $year  = (int) $year;
        $month = (int) $month;

        // Month start / end
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd   = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        $monthLabel = $monthStart->format('1-M-y') . ' to ' . $monthEnd->format('d-M-y');

        // ── Financial Year for opening balance calc ──────────────────────────
        $fyStartYear = ($month >= 4) ? $year : $year - 1;
        $fyStart     = Carbon::create($fyStartYear, 4, 1)->startOfDay();

        // ── Opening Balance: stock at start of this month ────────────────────
        // = current stock − (all FY inwards up to now) + (all FY outwards up to now)
        //   then add back this month's net

        $totalInBeforeMonth = (int) DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.user_id', $userId)
            ->where('purchase_items.product_id', $productId)
            ->whereBetween('purchases.created_at', [$fyStart, $monthStart->copy()->subSecond()])
            ->sum('purchase_items.quantity');

        $totalOutBeforeMonth = (int) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId)
            ->where('sale_items.product_id', $productId)
            ->whereBetween('sales.created_at', [$fyStart, $monthStart->copy()->subSecond()])
            ->sum('sale_items.quantity');

        $totalInFY = (int) DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.user_id', $userId)
            ->where('purchase_items.product_id', $productId)
            ->sum('purchase_items.quantity');

        $totalOutFY = (int) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId)
            ->where('sale_items.product_id', $productId)
            ->sum('sale_items.quantity');

        $currentStock = (int)($product->stock_quantity ?? 0);
        $fyOpeningQty = max(0, $currentStock - $totalInFY + $totalOutFY);

        $openingQty   = $fyOpeningQty + $totalInBeforeMonth - $totalOutBeforeMonth;
        $sellingPrice = (float)($product->price ?? 0);
        $openingValue = round($openingQty * $sellingPrice, 2);

        // ── Inwards this month (purchases) ────────────────────────────────────
        $purchases = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.user_id', $userId)
            ->where('purchase_items.product_id', $productId)
            ->whereBetween('purchases.created_at', [$monthStart, $monthEnd])
            ->select(
                'purchases.created_at as txn_date',
                'suppliers.name as party_name',
                DB::raw("'Purchase' as vch_type"),
                'purchases.id as vch_no',
                'purchase_items.quantity as qty',
                DB::raw('COALESCE(purchase_items.base_price, purchase_items.price, 0) as unit_price')
            )
            ->orderBy('purchases.created_at', 'asc')
            ->get();

        // ── Outwards this month (sales) ───────────────────────────────────────
        $sales = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId)
            ->where('sale_items.product_id', $productId)
            ->whereBetween('sales.created_at', [$monthStart, $monthEnd])
            ->select(
                'sales.created_at as txn_date',
                'customers.name as party_name',
                DB::raw("'Sales' as vch_type"),
                'sales.id as vch_no',
                'sale_items.quantity as qty',
                DB::raw('COALESCE(sale_items.base_price, sale_items.price, 0) as unit_price')
            )
            ->orderBy('sales.created_at', 'asc')
            ->get();

        // ── Merge & sort transactions ─────────────────────────────────────────
        $transactions = collect();

        foreach ($purchases as $p) {
            $transactions->push([
                'date'       => Carbon::parse($p->txn_date)->format('d-M-y'),
                'sort_date'  => Carbon::parse($p->txn_date)->timestamp,
                'party'      => $p->party_name,
                'vch_type'   => 'Purchase',
                'vch_no'     => $p->vch_no,
                'in_qty'     => (int)$p->qty,
                'in_value'   => round($p->qty * $p->unit_price, 2),
                'out_qty'    => 0,
                'out_value'  => 0.0,
            ]);
        }

        foreach ($sales as $s) {
            $transactions->push([
                'date'       => Carbon::parse($s->txn_date)->format('d-M-y'),
                'sort_date'  => Carbon::parse($s->txn_date)->timestamp,
                'party'      => $s->party_name,
                'vch_type'   => 'Sales',
                'vch_no'     => $s->vch_no,
                'in_qty'     => 0,
                'in_value'   => 0.0,
                'out_qty'    => (int)$s->qty,
                'out_value'  => round($s->qty * $s->unit_price, 2),
            ]);
        }

        $transactions = $transactions->sortBy('sort_date')->values();

        // ── Compute running closing balance per row ──────────────────────────
        $runningQty   = $openingQty;
        $rows         = [];
        $totalInQty   = 0;
        $totalInVal   = 0.0;
        $totalOutQty  = 0;
        $totalOutVal  = 0.0;

        foreach ($transactions as $txn) {
            $runningQty  += $txn['in_qty'] - $txn['out_qty'];
            $closingVal   = round($runningQty * $sellingPrice, 2);

            $totalInQty  += $txn['in_qty'];
            $totalInVal  += $txn['in_value'];
            $totalOutQty += $txn['out_qty'];
            $totalOutVal += $txn['out_value'];

            $rows[] = [
                'date'          => $txn['date'],
                'party'         => $txn['party'],
                'vch_type'      => $txn['vch_type'],
                'vch_no'        => $txn['vch_no'],
                'in_qty'        => $txn['in_qty'],
                'in_value'      => $txn['in_value'],
                'out_qty'       => $txn['out_qty'],
                'out_value'     => round($txn['out_value'], 2),
                'closing_qty'   => $runningQty,
                'closing_value' => $closingVal,
            ];
        }

        return Inertia::render('Reports/StockItemMonthDetail', [
            'product'      => [
                'id'        => $product->id,
                'name'      => $product->name,
                'sku'       => $product->sku,
                'unit_type' => $product->unit_type ?? '',
                'price'     => $sellingPrice,
                'category'  => $product->category?->name ?? '—',
            ],
            'storeName'    => $storeName,
            'monthLabel'   => $monthLabel,
            'monthName'    => $monthStart->format('F Y'),
            'year'         => $year,
            'month'        => $month,
            'openingQty'   => $openingQty,
            'openingValue' => $openingValue,
            'rows'         => $rows,
            'totalInQty'   => $totalInQty,
            'totalInVal'   => round($totalInVal, 2),
            'totalOutQty'  => $totalOutQty,
            'totalOutVal'  => round($totalOutVal, 2),
            'closingQty'   => $runningQty,
            'closingVal'   => round($runningQty * $sellingPrice, 2),
        ]);
    }
}
