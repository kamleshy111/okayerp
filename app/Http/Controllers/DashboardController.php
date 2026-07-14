<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SaleItem;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Income;
use App\Models\SaleReturn;
use App\Models\PurchaseReturn;
use DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        $role = $user->role;

        if($role === 'admin'){

            $previousMonth = Carbon::now()->subMonth();
            $now = Carbon::now();

            $totalStores = User::where('role', 'store')->count();
            $totalCustomers = Customer::count();
            $totalSuppliers = Supplier::count();
            $totalCategories = Category::count();
            $totalProducts = Product::count();
            $totalStockProducts = Product::sum('stock_quantity');
            $totalSaleProducts = SaleItem::sum('quantity');

            //stores in percentage Change
            $lastMonthStore = User::where('role', 'store')->whereMonth('created_at', $previousMonth->month)->whereYear('created_at', $previousMonth->year)->count();
            $thisMonthStore = User::where('role', 'store')->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();

            $percentageChangeStore = 0;
            if ($lastMonthStore > 0) {
                $percentageChangeStore = (($thisMonthStore - $lastMonthStore) / $lastMonthStore) * 100;
            }
            $percentageChangeStore = round($percentageChangeStore, 2);  

            //product percentage Change
            $lastMonthProduct = Product::whereMonth('created_at', $previousMonth->month)->whereYear('created_at', $previousMonth->year)->count();
            $thisMonthProduct = Product::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();

            $percentageChangeProduct = 0;
            if ($lastMonthProduct > 0) {
                $percentageChangeProduct = (($thisMonthProduct - $lastMonthProduct) / $lastMonthProduct) * 100;
            }
            $percentageChangeProduct = round($percentageChangeProduct, 2);  

            //customer percentage Change
            $lastMonthCustomer = Customer::whereMonth('created_at', $previousMonth->month)->whereYear('created_at', $previousMonth->year)->count();
            $thisMonthCustomer = Customer::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();

            $percentageChangeCustomer = 0;
            if ($lastMonthCustomer > 0) {
                $percentageChangeCustomer = (($thisMonthCustomer - $lastMonthCustomer) / $lastMonthCustomer) * 100;
            }
            $percentageChangeCustomer = round($percentageChangeCustomer, 2); 

            //Supplier percentage Change
            $lastMonthSupplier = Supplier::whereMonth('created_at', $previousMonth->month)->whereYear('created_at', $previousMonth->year)->count();
            $thisMonthSupplier = Supplier::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();

            $percentageChangeSupplier = 0;
            if ($lastMonthSupplier > 0) {
                $percentageChangeSupplier = (($thisMonthSupplier - $lastMonthSupplier) / $lastMonthSupplier) * 100;
            }
            $percentageChangeSupplier = round($percentageChangeSupplier, 2); 

            // sale product  percentage Change
            $lastMonthSales = SaleItem::whereMonth('created_at', $previousMonth->month)->whereYear('created_at', $previousMonth->year)->sum('quantity');

            $thisMonthSales = SaleItem::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->sum('quantity');

            $percentageChangeSale = 0;
            if ($lastMonthSales > 0) {
                $percentageChangeSale = (($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100;
            }
            $percentageChangeSale = round($percentageChangeSale, 2);

        //purchases product  percentage Change
        $lastMonthPurchases = PurchaseItem::whereMonth('created_at', $previousMonth->month)->whereYear('created_at', $previousMonth->year)->sum('quantity');

        $thisMonthPurchases = PurchaseItem::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->sum('quantity');

        $percentageChangePurchases = 0;
        if ($lastMonthPurchases > 0) {
            $percentageChangePurchases = (($thisMonthPurchases - $lastMonthPurchases) / $lastMonthPurchases) * 100;
        }
        $percentageChangePurchases = round($percentageChangePurchases, 2);

            return Inertia::render('Admin/Dashboard', [
                'role'   => $role,
                'totalStores' => $totalStores,
                'totalCustomers' => $totalCustomers,
                'totalSuppliers' => $totalSuppliers,
                'totalCategories' => $totalCategories,
                'totalProducts' => $totalProducts,
                'totalStockProducts' => $totalStockProducts,
                'totalSaleProducts' => $totalSaleProducts,
                'percentageChangeStore' => $percentageChangeStore,
                'percentageChangeProduct' => $percentageChangeProduct,
                'percentageChangeCustomer' => $percentageChangeCustomer,
                'percentageChangeSupplier' => $percentageChangeSupplier,
                'percentageChangeSale' => $percentageChangeSale,
                'percentageChangePurchases' => $percentageChangePurchases,
            ]);
        }

        $userId = Auth::user()->id;
        $previousMonth = Carbon::now()->subMonth();
        $now = Carbon::now();

        $totalProducts = Product::where('user_id', $userId)->count();
        $totalCustomers = Customer::where('user_id', $userId)->count();
        $totalSuppliers = Supplier::where('user_id', $userId)->count();
        $totalCategories = Category::where('user_id', $userId)->count();
        $totalStockProducts = Product::where('user_id', $userId)->sum('stock_quantity');

        $totalSaleProducts = \DB::table('sales')
                            ->join('customers', 'sales.customer_id', '=', 'customers.id')
                            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
                            ->where('customers.user_id', $userId)
                            ->sum('sale_items.quantity');

        $returnedSaleProducts = \DB::table('sale_returns')
                            ->join('sale_return_items', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
                            ->where('sale_returns.user_id', $userId)
                            ->sum('sale_return_items.quantity');

        $totalSaleProducts = max(0, $totalSaleProducts - $returnedSaleProducts);

        //purchases product  percentage Change
        $lastMonthPurchases = \DB::table('purchase_items')
                            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
                            ->where('suppliers.user_id', $userId)
                            ->whereMonth('purchase_items.created_at', $previousMonth->month)
                            ->whereYear('purchase_items.created_at', $previousMonth->year)
                            ->sum('purchase_items.quantity');

        $lastMonthPurchaseReturns = \DB::table('purchase_returns')
                            ->join('purchase_return_items', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
                            ->where('purchase_returns.user_id', $userId)
                            ->whereMonth('purchase_returns.return_date', $previousMonth->month)
                            ->whereYear('purchase_returns.return_date', $previousMonth->year)
                            ->sum('purchase_return_items.quantity');

        $lastMonthPurchases = max(0, $lastMonthPurchases - $lastMonthPurchaseReturns);

        $thisMonthPurchases = \DB::table('purchase_items')
                            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
                            ->where('suppliers.user_id', $userId)
                            ->whereMonth('purchase_items.created_at', $now->month)
                            ->whereYear('purchase_items.created_at', $now->year)
                            ->sum('purchase_items.quantity');

        $thisMonthPurchaseReturns = \DB::table('purchase_returns')
                            ->join('purchase_return_items', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
                            ->where('purchase_returns.user_id', $userId)
                            ->whereMonth('purchase_returns.return_date', $now->month)
                            ->whereYear('purchase_returns.return_date', $now->year)
                            ->sum('purchase_return_items.quantity');

        $thisMonthPurchases = max(0, $thisMonthPurchases - $thisMonthPurchaseReturns);

        $percentageChangePurchases = 0;
        if ($lastMonthPurchases > 0) {
            $percentageChangePurchases = (($thisMonthPurchases - $lastMonthPurchases) / $lastMonthPurchases) * 100;
        }
        $percentageChangePurchases = round($percentageChangePurchases, 2);


        // sale product  percentage Change
        $lastMonthSales = \DB::table('sales')
                            ->join('customers', 'sales.customer_id', '=', 'customers.id')
                            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
                            ->where('customers.user_id', $userId)
                            ->whereMonth('sales.created_at', $previousMonth->month)
                            ->whereYear('sales.created_at', $previousMonth->year)
                            ->sum('sale_items.quantity');

        $lastMonthSaleReturns = \DB::table('sale_returns')
                            ->join('sale_return_items', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
                            ->where('sale_returns.user_id', $userId)
                            ->whereMonth('sale_returns.return_date', $previousMonth->month)
                            ->whereYear('sale_returns.return_date', $previousMonth->year)
                            ->sum('sale_return_items.quantity');

        $lastMonthSales = max(0, $lastMonthSales - $lastMonthSaleReturns);

        $thisMonthSales = \DB::table('sales')
                            ->join('customers', 'sales.customer_id', '=', 'customers.id')
                            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
                            ->where('customers.user_id', $userId)
                            ->whereMonth('sales.created_at', $now->month)
                            ->whereYear('sales.created_at', $now->year)
                            ->sum('sale_items.quantity');

        $thisMonthSaleReturns = \DB::table('sale_returns')
                            ->join('sale_return_items', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
                            ->where('sale_returns.user_id', $userId)
                            ->whereMonth('sale_returns.return_date', $now->month)
                            ->whereYear('sale_returns.return_date', $now->year)
                            ->sum('sale_return_items.quantity');

        $thisMonthSales = max(0, $thisMonthSales - $thisMonthSaleReturns);

        $percentageChangeSale = 0;
        if ($lastMonthSales > 0) {
            $percentageChangeSale = (($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100;
        }
        $percentageChangeSale = round($percentageChangeSale, 2);

        //product percentage Change
        $lastMonthProduct = Product::where('user_id', $userId)->whereMonth('created_at', $previousMonth->month)->whereYear('created_at', $previousMonth->year)->count();
        $thisMonthProduct = Product::where('user_id', $userId)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();

        $percentageChangeProduct = 0;
        if ($lastMonthProduct > 0) {
            $percentageChangeProduct = (($thisMonthProduct - $lastMonthProduct) / $lastMonthProduct) * 100;
        }
        $percentageChangeProduct = round($percentageChangeProduct, 2);  
        
        //Customers percentage Change
        $lastMonthCustomer = Customer::where('user_id', $userId)->whereMonth('created_at', $previousMonth->month)->whereYear('created_at', $previousMonth->year)->count();
        $thisMonthCustomer = Customer::where('user_id', $userId)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();

        $percentageChangeCustomer = 0;
        if ($lastMonthCustomer > 0) {
            $percentageChangeCustomer = (($thisMonthCustomer - $lastMonthCustomer) / $lastMonthCustomer) * 100;
        }
        $percentageChangeCustomer = round($percentageChangeCustomer, 2);

        //Suppliers percentage Change
        $lastMonthSupplier = Supplier::where('user_id', $userId)->whereMonth('created_at', $previousMonth->month)->whereYear('created_at', $previousMonth->year)->count();
        $thisMonthSupplier = Supplier::where('user_id', $userId)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();

        $percentageChangeSupplier = 0;
        if ($lastMonthSupplier > 0) {
            $percentageChangeSupplier = (($thisMonthSupplier - $lastMonthSupplier) / $lastMonthSupplier) * 100;
        }
        $percentageChangeSupplier = round($percentageChangeSupplier, 2);

        // Get profit and loss data for dynamic months
        $monthsToFetch = (int) $request->input('months', 6);
        if ($monthsToFetch < 1 || $monthsToFetch > 12) {
            $monthsToFetch = 6;
        }

        $profitLossData = [];
        for ($i = $monthsToFetch - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $month = $date->month;
            $year = $date->year;
            $monthName = $date->format('M Y');

            $salesSum = Sale::whereHas('customer', fn($q) => $q->where('user_id', $userId))
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->sum('grand_total');

            $salesReturnSum = SaleReturn::where('user_id', $userId)
                ->whereMonth('return_date', $month)
                ->whereYear('return_date', $year)
                ->get()
                ->sum(fn($r) => $r->refund_amount + $r->gst_refund_amount);

            $netSales = max(0, $salesSum - $salesReturnSum);

            $purchasesSum = Purchase::whereHas('supplier', fn($q) => $q->where('user_id', $userId))
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->sum('grand_total');

            $purchasesReturnSum = PurchaseReturn::where('user_id', $userId)
                ->whereMonth('return_date', $month)
                ->whereYear('return_date', $year)
                ->get()
                ->sum(fn($r) => $r->refund_amount + $r->gst_refund_amount);

            $netPurchases = max(0, $purchasesSum - $purchasesReturnSum);

            $expensesSum = Expense::where('user_id', $userId)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            $incomeSum = Income::where('user_id', $userId)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');

            $profit = $netSales + $incomeSum - $netPurchases - $expensesSum;

            $profitLossData[] = [
                'month' => $monthName,
                'sales' => round($netSales, 2),
                'purchases' => round($netPurchases, 2),
                'expenses' => round($expensesSum, 2),
                'incomes' => round($incomeSum, 2),
                'profit' => round($profit, 2),
            ];
        }

        $privateLedgerUnlocked = session('private_ledger_unlocked') === true;

        $customers = Customer::where('user_id', $userId)
            ->with(['sales', 'payments', 'sales.saleReturns'])
            ->get();
        
        $totalCustomerDue = $customers->sum(function ($customer) use ($privateLedgerUnlocked) {
            $dueAmount = 0;
            $advanceAmount = 0;
            foreach ($customer->sales as $sale) {
                $paymentsSum = \App\Models\SalePayment::where('sale_id', $sale->id);
                $actualPaid = $paymentsSum->sum('amount');

                $dueDeductionsSum = (float)$sale->saleReturnItems->sum('due_deduction');

                // Sum of store credit refunds on this sale
                $storeCreditRefundsSum = (float)$sale->saleReturns
                    ->where('refund_method', 'Store Credit')
                    ->sum(fn($r) => (float)$r->refund_amount + (float)$r->gst_refund_amount);

                $saleBalance = $actualPaid - (float)$sale->grand_total + $dueDeductionsSum;
                if ($saleBalance < 0) {
                    $dueAmount += abs($saleBalance);
                } elseif ($saleBalance > 0) {
                    $advanceAmount += $saleBalance;
                }

                $advanceAmount += $storeCreditRefundsSum;
            }

            $totalDirectPaid = $customer->payments->where('sale_id', null)->sum('amount');
            $advanceAmount += $totalDirectPaid;

            $netBalance = $advanceAmount - $dueAmount;
            return $netBalance < 0 ? abs($netBalance) : 0;
        });

        $suppliers = Supplier::where('user_id', $userId)
            ->with(['purchases', 'purchasePayments', 'purchases.purchaseReturns'])
            ->get();

        $totalSupplierDue = $suppliers->sum(function ($supplier) use ($privateLedgerUnlocked) {
            $dueAmount = 0;
            $advanceAmount = 0;
            foreach ($supplier->purchases as $purchase) {
                $paymentsSum = \App\Models\PurchasePayment::where('purchase_id', $purchase->id);
                $actualPaid = $paymentsSum->sum('amount');

                // Sum of due_deduction for returns on this purchase
                $dueDeductionsSum = (float)$purchase->purchaseReturnItems->sum('due_deduction');

                // Sum of store credit refunds on this purchase
                $storeCreditRefundsSum = (float)$purchase->purchaseReturns
                    ->where('refund_method', 'Store Credit')
                    ->sum(fn($r) => (float)$r->refund_amount + (float)$r->gst_refund_amount);

                $purchaseBalance = $actualPaid - (float)$purchase->grand_total + $dueDeductionsSum;
                if ($purchaseBalance < 0) {
                    $dueAmount += abs($purchaseBalance);
                } elseif ($purchaseBalance > 0) {
                    $advanceAmount += $purchaseBalance;
                }

                $advanceAmount += $storeCreditRefundsSum;
            }

            $totalDirectPaid = $supplier->purchasePayments->where('purchase_id', null)->sum('amount');
            $advanceAmount += $totalDirectPaid;

            $netBalance = $advanceAmount - $dueAmount;
            return $netBalance < 0 ? abs($netBalance) : 0;
        });

        return Inertia::render('Dashboard', [
            'role' => $role,
            'totalProducts' => $totalProducts,
            'totalCustomers' => $totalCustomers,
            'totalSuppliers' => $totalSuppliers,
            'totalCategories' => $totalCategories,
            'totalStockProducts' => $totalStockProducts,
            'totalSaleProducts' => $totalSaleProducts,
            'percentageChangeSale' => $percentageChangeSale,
            'percentageChangeProduct' => $percentageChangeProduct,
            'percentageChangeCustomer' => $percentageChangeCustomer,
            'percentageChangeSupplier' => $percentageChangeSupplier,
            'percentageChangePurchases' => $percentageChangePurchases,
            'profitLossData' => $profitLossData,
            'totalCustomerDue' => round((float)$totalCustomerDue, 2),
            'totalSupplierDue' => round((float)$totalSupplierDue, 2),
        ]);
    }
}
