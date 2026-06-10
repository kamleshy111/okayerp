<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\SalePayment;
use App\Models\PurchasePayment;
use App\Models\Expense;
use App\Services\AccountingService;

class RepostAccountingEntries extends Command
{
    protected $signature   = 'accounting:repost {--user= : Only repost for a specific user_id}';
    protected $description = 'Retroactively posts all existing transactions into the double-entry ledger (journal_entries table).';

    public function handle(): int
    {
        $userFilter = $this->option('user');

        // ---------- SALES ----------
        $salesQuery = Sale::with('customer');
        if ($userFilter) {
            $salesQuery->whereHas('customer', fn ($q) => $q->where('user_id', $userFilter));
        }
        $sales = $salesQuery->get();

        $this->info("Re-posting {$sales->count()} sales...");
        foreach ($sales as $sale) {
            // Determine user_id from customer relationship
            $uid = optional($sale->customer)->user_id;
            if (!$uid) continue;
            $svc = new AccountingService($uid);
            $svc->postSale($sale);
        }

        // ---------- PURCHASES ----------
        $purchasesQuery = Purchase::with('supplier');
        if ($userFilter) {
            $purchasesQuery->whereHas('supplier', fn ($q) => $q->where('user_id', $userFilter));
        }
        $purchases = $purchasesQuery->get();

        $this->info("Re-posting {$purchases->count()} purchases...");
        foreach ($purchases as $purchase) {
            $uid = optional($purchase->supplier)->user_id;
            if (!$uid) continue;
            $svc = new AccountingService($uid);
            $svc->postPurchase($purchase);
        }

        // ---------- SALE PAYMENTS ----------
        $salePaymentsQuery = SalePayment::with('customer');
        if ($userFilter) {
            $salePaymentsQuery->whereHas('customer', fn ($q) => $q->where('user_id', $userFilter));
        }
        $salePayments = $salePaymentsQuery->get();

        $this->info("Re-posting {$salePayments->count()} sale payments...");
        foreach ($salePayments as $payment) {
            $uid = optional($payment->customer)->user_id;
            if (!$uid) continue;
            $svc = new AccountingService($uid);
            $svc->postCustomerPayment($payment);
        }

        // ---------- PURCHASE PAYMENTS ----------
        $purchasePaymentsQuery = PurchasePayment::with('supplier');
        if ($userFilter) {
            $purchasePaymentsQuery->whereHas('supplier', fn ($q) => $q->where('user_id', $userFilter));
        }
        $purchasePayments = $purchasePaymentsQuery->get();

        $this->info("Re-posting {$purchasePayments->count()} purchase payments...");
        foreach ($purchasePayments as $payment) {
            $uid = optional($payment->supplier)->user_id;
            if (!$uid) continue;
            $svc = new AccountingService($uid);
            $svc->postSupplierPayment($payment);
        }

        // ---------- EXPENSES ----------
        $expensesQuery = Expense::with('category');
        if ($userFilter) {
            $expensesQuery->where('user_id', $userFilter);
        }
        $expenses = $expensesQuery->get();

        $this->info("Re-posting {$expenses->count()} expenses...");
        foreach ($expenses as $expense) {
            $uid = $expense->user_id;
            if (!$uid) continue;
            $svc = new AccountingService($uid);
            $svc->postExpense($expense);
        }

        $this->info('✅ All accounting entries have been re-posted successfully!');

        return self::SUCCESS;
    }
}
