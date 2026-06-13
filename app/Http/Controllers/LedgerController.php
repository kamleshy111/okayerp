<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\SalePayment;
use App\Models\PurchasePayment;
use App\Models\Expense;
use App\Services\AccountingService;
use App\Models\SaleReturn;
use App\Models\PurchaseReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $user = Auth::user();
        $hasPin = !empty($user->ledger_pin);
        
        // Default ledger type to standard (accepted = 1) unless specified
        $ledgerType = $request->input('ledger_type', 'standard'); 
        $accepted = ($ledgerType === 'private') ? 0 : 1;
        
        // Check if private ledger is unlocked if they requested private ledger
        $isPrivateUnlocked = session('private_ledger_unlocked') === true;
        
        if ($accepted === 0 && !$isPrivateUnlocked) {
            return Inertia::render('Reports/Accounting', [
                'unlocked' => false,
                'hasPin' => $hasPin,
                'ledgerType' => 'private',
                'trialBalance' => [
                    'items' => [],
                    'total_debit' => 0,
                    'total_credit' => 0
                ],
                'profitAndLoss' => [
                    'revenue_items' => [],
                    'expense_items' => [],
                    'total_revenue' => 0,
                    'total_expense' => 0,
                    'net_profit' => 0
                ],
                'balanceSheet' => [
                    'asset_items' => [],
                    'liability_items' => [],
                    'equity_items' => [],
                    'total_assets' => 0,
                    'total_liabilities' => 0,
                    'total_equity' => 0,
                    'total_liabilities_and_equity' => 0
                ],
                'startDate' => $request->input('start_date'),
                'endDate' => $request->input('end_date'),
            ]);
        }

        // Parse date filters
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Fetch accounts for the user with correct accepted flag
        $accounts = Account::where('user_id', $userId)
            ->where('accepted', $accepted)
            ->get();

        // 1. Trial Balance Calculation
        $trialBalance = [];
        $totalDebitSum = 0.0;
        $totalCreditSum = 0.0;

        foreach ($accounts as $account) {
            $query = JournalEntry::where('user_id', $userId)
                ->where('account_id', $account->id)
                ->where('accepted', $accepted);

            if ($startDate) {
                $query->where('entry_date', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('entry_date', '<=', $endDate);
            }

            $debits = (double) $query->clone()->where('type', 'debit')->sum('amount');
            $credits = (double) $query->clone()->where('type', 'credit')->sum('amount');

            $netDebit = 0.0;
            $netCredit = 0.0;

            if ($debits > $credits) {
                $netDebit = $debits - $credits;
            } elseif ($credits > $debits) {
                $netCredit = $credits - $debits;
            }

            // Only list accounts with transactions or non-zero net balance
            if ($debits > 0 || $credits > 0) {
                $trialBalance[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'debit' => round($netDebit, 2),
                    'credit' => round($netCredit, 2),
                ];
                $totalDebitSum += $netDebit;
                $totalCreditSum += $netCredit;
            }
        }

        // Sort trial balance by account code
        usort($trialBalance, function ($a, $b) {
            return strcmp($a['code'], $b['code']);
        });

        // 2. Profit & Loss Statement (Revenue & Expenses)
        $revenueItems = [];
        $expenseItems = [];
        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($accounts as $account) {
            if ($account->type !== 'Revenue' && $account->type !== 'Expense') {
                continue;
            }

            $query = JournalEntry::where('user_id', $userId)
                ->where('account_id', $account->id)
                ->where('accepted', $accepted);

            if ($startDate) {
                $query->where('entry_date', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('entry_date', '<=', $endDate);
            }

            $debits = (double) $query->clone()->where('type', 'debit')->sum('amount');
            $credits = (double) $query->clone()->where('type', 'credit')->sum('amount');

            if ($account->type === 'Revenue') {
                $balance = $credits - $debits;
                if ($balance != 0 || $debits > 0 || $credits > 0) {
                    $revenueItems[] = [
                        'code' => $account->code,
                        'name' => $account->name,
                        'balance' => round($balance, 2),
                    ];
                    $totalRevenue += $balance;
                }
            } elseif ($account->type === 'Expense') {
                $balance = $debits - $credits;
                if ($balance != 0 || $debits > 0 || $credits > 0) {
                    $expenseItems[] = [
                        'code' => $account->code,
                        'name' => $account->name,
                        'balance' => round($balance, 2),
                    ];
                    $totalExpense += $balance;
                }
            }
        }

        $netProfit = $totalRevenue - $totalExpense;

        $profitAndLoss = [
            'revenue_items' => $revenueItems,
            'expense_items' => $expenseItems,
            'total_revenue' => round($totalRevenue, 2),
            'total_expense' => round($totalExpense, 2),
            'net_profit' => round($netProfit, 2),
        ];

        // 3. Balance Sheet (Assets, Liabilities, Equity)
        $assetItems = [];
        $liabilityItems = [];
        $equityItems = [];
        
        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        foreach ($accounts as $account) {
            if ($account->type !== 'Asset' && $account->type !== 'Liability' && $account->type !== 'Equity') {
                continue;
            }

            $query = JournalEntry::where('user_id', $userId)
                ->where('account_id', $account->id)
                ->where('accepted', $accepted);

            if ($endDate) {
                $query->where('entry_date', '<=', $endDate);
            }

            $debits = (double) $query->clone()->where('type', 'debit')->sum('amount');
            $credits = (double) $query->clone()->where('type', 'credit')->sum('amount');

            if ($account->type === 'Asset') {
                $balance = $debits - $credits;
                if ($balance != 0 || $debits > 0 || $credits > 0) {
                    $assetItems[] = [
                        'code' => $account->code,
                        'name' => $account->name,
                        'balance' => round($balance, 2),
                    ];
                    $totalAssets += $balance;
                }
            } elseif ($account->type === 'Liability') {
                $balance = $credits - $debits;
                if ($balance != 0 || $debits > 0 || $credits > 0) {
                    $liabilityItems[] = [
                        'code' => $account->code,
                        'name' => $account->name,
                        'balance' => round($balance, 2),
                    ];
                    $totalLiabilities += $balance;
                }
            } elseif ($account->type === 'Equity') {
                $balance = $credits - $debits;
                if ($balance != 0 || $debits > 0 || $credits > 0) {
                    $equityItems[] = [
                        'code' => $account->code,
                        'name' => $account->name,
                        'balance' => round($balance, 2),
                    ];
                    $totalEquity += $balance;
                }
            }
        }

        // Cumulative Net Profit up to endDate for Balance Sheet
        $cumRevenue = 0.0;
        $cumExpense = 0.0;
        foreach ($accounts as $account) {
            if ($account->type === 'Revenue' || $account->type === 'Expense') {
                $query = JournalEntry::where('user_id', $userId)
                    ->where('account_id', $account->id)
                    ->where('accepted', $accepted);
                if ($endDate) {
                    $query->where('entry_date', '<=', $endDate);
                }
                $debits = (double) $query->clone()->where('type', 'debit')->sum('amount');
                $credits = (double) $query->clone()->where('type', 'credit')->sum('amount');
                if ($account->type === 'Revenue') {
                    $cumRevenue += ($credits - $debits);
                } else {
                    $cumExpense += ($debits - $credits);
                }
            }
        }
        $cumulativeNetProfit = $cumRevenue - $cumExpense;

        $equityItems[] = [
            'code' => '3999',
            'name' => 'Retained Earnings (Net Profit)',
            'balance' => round($cumulativeNetProfit, 2),
        ];
        $totalEquity += $cumulativeNetProfit;

        $balanceSheet = [
            'asset_items' => $assetItems,
            'liability_items' => $liabilityItems,
            'equity_items' => $equityItems,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => round($totalEquity, 2),
            'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
        ];

        return Inertia::render('Reports/Accounting', [
            'unlocked' => true,
            'hasPin' => $hasPin,
            'ledgerType' => $ledgerType,
            'trialBalance' => [
                'items' => $trialBalance,
                'total_debit' => round($totalDebitSum, 2),
                'total_credit' => round($totalCreditSum, 2),
            ],
            'profitAndLoss' => $profitAndLoss,
            'balanceSheet' => $balanceSheet,
            'startDate'    => $startDate,
            'endDate'      => $endDate,
        ]);
    }

    /**
     * Retroactively re-post all journal entries for the current user.
     * Useful to sync the ledger after adding the accounting system to an existing app.
     */
    public function repost(Request $request)
    {
        $userId = Auth::id();
        $svc    = new AccountingService($userId);

        // Sales
        $sales = Sale::whereHas('customer', fn($q) => $q->where('user_id', $userId))->get();
        foreach ($sales as $sale) { $svc->postSale($sale); }

        // Purchases
        $purchases = Purchase::whereHas('supplier', fn($q) => $q->where('user_id', $userId))->get();
        foreach ($purchases as $purchase) { $svc->postPurchase($purchase); }

        // Customer Payments (exclude down payments linked to sales as they are already posted via postSale)
        $salePayments = SalePayment::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->whereNull('sale_id')
            ->get();
        foreach ($salePayments as $p) { $svc->postCustomerPayment($p); }

        // Supplier Payments (exclude down payments linked to purchases as they are already posted via postPurchase)
        $purchasePayments = PurchasePayment::whereHas('supplier', fn($q) => $q->where('user_id', $userId))
            ->whereNull('purchase_id')
            ->get();
        foreach ($purchasePayments as $p) { $svc->postSupplierPayment($p); }

        // Expenses
        $expenses = Expense::where('user_id', $userId)->with('category')->get();
        foreach ($expenses as $expense) { $svc->postExpense($expense); }

        // Sales Returns
        $saleReturns = SaleReturn::where('user_id', $userId)->get();
        foreach ($saleReturns as $sr) { $svc->postSaleReturn($sr); }

        // Purchase Returns
        $purchaseReturns = PurchaseReturn::where('user_id', $userId)->get();
        foreach ($purchaseReturns as $pr) { $svc->postPurchaseReturn($pr); }

        return redirect()->route('reports.ledger')
            ->with('success', 'Ledger re-synced: ' . $sales->count() . ' sales, ' . $purchases->count() . ' purchases, ' . ($salePayments->count() + $purchasePayments->count()) . ' payments, ' . ($saleReturns->count() + $purchaseReturns->count()) . ' returns posted.');
    }
}
