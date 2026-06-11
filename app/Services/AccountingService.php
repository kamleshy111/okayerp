<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    private $userId;
    
    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Get or create a default account for the given type and name.
     */
    public function getAccount($name, $type, $code, $accepted)
    {
        return Account::firstOrCreate(
            [
                'user_id' => $this->userId,
                'name' => $name,
                'accepted' => $accepted,
            ],
            [
                'type' => $type,
                'code' => $code,
            ]
        );
    }

    public function getDefaultAccounts($accepted)
    {
        return [
            'Cash' => $this->getAccount('Cash', 'Asset', '1000', $accepted),
            'AR' => $this->getAccount('Accounts Receivable', 'Asset', '1200', $accepted),
            'Inventory' => $this->getAccount('Inventory Asset', 'Asset', '1500', $accepted),
            'AP' => $this->getAccount('Accounts Payable', 'Liability', '2000', $accepted),
            'GST_Liability' => $this->getAccount('GST Output (Liability)', 'Liability', '2200', $accepted),
            'GST_Receivable' => $this->getAccount('GST Input (Asset)', 'Asset', '1300', $accepted),
            'Sales' => $this->getAccount('Sales Revenue', 'Revenue', '4000', $accepted),
            'Purchases' => $this->getAccount('Purchase Expenses', 'Expense', '5000', $accepted),
            'OperatingExpenses' => $this->getAccount('Operating Expenses', 'Expense', '6000', $accepted),
        ];
    }

    /**
     * Helper to post an entry
     */
    private function postEntry($accountId, $refType, $refId, $type, $amount, $date, $description, $accepted)
    {
        if ($amount <= 0) return;

        JournalEntry::create([
            'user_id' => $this->userId,
            'account_id' => $accountId,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'type' => $type,
            'amount' => $amount,
            'entry_date' => $date,
            'description' => $description,
            'accepted' => $accepted,
        ]);
    }

    /**
     * Clear existing journal entries for a reference to avoid duplicates on update
     */
    public function clearEntries($refType, $refId)
    {
        JournalEntry::where('user_id', $this->userId)
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->delete();
    }

    public function postSale($sale)
    {
        $this->clearEntries('Sale', $sale->id);

        $accounts = $this->getDefaultAccounts($sale->accepted);
        $date = $sale->created_at ? $sale->created_at->toDateString() : now()->toDateString();
        $desc = "Sale Invoice #{$sale->id}" . ($sale->invoice_no ? " ({$sale->invoice_no})" : "");

        $baseAmount = $sale->total_amount;
        $gstAmount = $sale->gst_amount;
        $grandTotal = $sale->grand_total;
        $paid = $sale->paid;
        $outstanding = $grandTotal - $paid;

        // Credits
        $this->postEntry($accounts['Sales']->id, 'Sale', $sale->id, 'credit', $baseAmount, $date, $desc, $sale->accepted);
        if ($gstAmount > 0) {
            $this->postEntry($accounts['GST_Liability']->id, 'Sale', $sale->id, 'credit', $gstAmount, $date, $desc, $sale->accepted);
        }

        // Debits
        if ($paid > 0) {
            $this->postEntry($accounts['Cash']->id, 'Sale', $sale->id, 'debit', $paid, $date, $desc . " (Down Payment)", $sale->accepted);
        }
        if ($outstanding > 0) {
            $this->postEntry($accounts['AR']->id, 'Sale', $sale->id, 'debit', $outstanding, $date, $desc, $sale->accepted);
        }
    }

    public function postPurchase($purchase)
    {
        $this->clearEntries('Purchase', $purchase->id);

        $accounts = $this->getDefaultAccounts($purchase->accepted);
        $date = $purchase->purchase_date ?? ($purchase->created_at ? $purchase->created_at->toDateString() : now()->toDateString());
        $desc = "Purchase Bill #{$purchase->id}" . ($purchase->invoice_no ? " ({$purchase->invoice_no})" : "");

        $baseAmount = $purchase->total_amount;
        $gstAmount = $purchase->gst_amount;
        $grandTotal = $purchase->grand_total;
        $paid = $purchase->paid;
        $outstanding = $grandTotal - $paid;

        // Debits
        $this->postEntry($accounts['Purchases']->id, 'Purchase', $purchase->id, 'debit', $baseAmount, $date, $desc, $purchase->accepted);
        if ($gstAmount > 0) {
            $this->postEntry($accounts['GST_Receivable']->id, 'Purchase', $purchase->id, 'debit', $gstAmount, $date, $desc, $purchase->accepted);
        }

        // Credits
        if ($paid > 0) {
            $this->postEntry($accounts['Cash']->id, 'Purchase', $purchase->id, 'credit', $paid, $date, $desc . " (Down Payment)", $purchase->accepted);
        }
        if ($outstanding > 0) {
            $this->postEntry($accounts['AP']->id, 'Purchase', $purchase->id, 'credit', $outstanding, $date, $desc, $purchase->accepted);
        }
    }

    public function postCustomerPayment($payment)
    {
        $this->clearEntries('SalePayment', $payment->id);

        $accounts = $this->getDefaultAccounts($payment->accepted);
        $date = $payment->payment_date ?? now()->toDateString();
        $desc = "Customer Payment #{$payment->id}";

        // Debit Cash, Credit AR
        $this->postEntry($accounts['Cash']->id, 'SalePayment', $payment->id, 'debit', $payment->amount, $date, $desc, $payment->accepted);
        $this->postEntry($accounts['AR']->id, 'SalePayment', $payment->id, 'credit', $payment->amount, $date, $desc, $payment->accepted);
    }

    public function postSupplierPayment($payment)
    {
        $this->clearEntries('PurchasePayment', $payment->id);

        $accounts = $this->getDefaultAccounts($payment->accepted);
        $date = $payment->payment_date ?? now()->toDateString();
        $desc = "Supplier Payment #{$payment->id}";

        // Debit AP, Credit Cash
        $this->postEntry($accounts['AP']->id, 'PurchasePayment', $payment->id, 'debit', $payment->amount, $date, $desc, $payment->accepted);
        $this->postEntry($accounts['Cash']->id, 'PurchasePayment', $payment->id, 'credit', $payment->amount, $date, $desc, $payment->accepted);
    }

    public function postExpense($expense)
    {
        $this->clearEntries('Expense', $expense->id);

        $accounts = $this->getDefaultAccounts($expense->accepted ?? 1); // fallback to 1 if accepted not present
        
        // Ensure specific expense category account if needed, or just use general Operating Expenses
        $expenseAccount = $accounts['OperatingExpenses'];
        if ($expense->category) {
            $expenseAccount = $this->getAccount($expense->category->name, 'Expense', '6100', $expense->accepted ?? 1);
        }

        $date = $expense->date ?? now()->toDateString();
        $note = $expense->note ?? $expense->description ?? 'N/A';
        $desc = "Expense #{$expense->id} - {$note}";

        // Debit Expense, Credit Cash
        $this->postEntry($expenseAccount->id, 'Expense', $expense->id, 'debit', $expense->amount, $date, $desc, $expense->accepted ?? 1);
        $this->postEntry($accounts['Cash']->id, 'Expense', $expense->id, 'credit', $expense->amount, $date, $desc, $expense->accepted ?? 1);
    }

    public function postSaleReturn($return)
    {
        $this->clearEntries('SaleReturn', $return->id);

        $sale = $return->sale;
        $accepted = $sale->accepted ?? 1;
        
        $accounts = $this->getDefaultAccounts($accepted);
        $date = $return->return_date ?? now()->toDateString();
        $desc = "Sale Return #{$return->return_no} for Invoice #{$sale->id}";

        $baseRefund = $return->refund_amount;
        $gstRefund = $return->gst_refund_amount;
        $totalRefund = $baseRefund + $gstRefund;

        // Debits (debit Sales and GST Output to reduce revenue & tax liability)
        $this->postEntry($accounts['Sales']->id, 'SaleReturn', $return->id, 'debit', $baseRefund, $date, $desc, $accepted);
        if ($gstRefund > 0) {
            $this->postEntry($accounts['GST_Liability']->id, 'SaleReturn', $return->id, 'debit', $gstRefund, $date, $desc, $accepted);
        }

        // Credits (credit Cash or AR to reflect cash paid out or receivable reduced)
        if ($return->refund_method === 'Store Credit') {
            $this->postEntry($accounts['AR']->id, 'SaleReturn', $return->id, 'credit', $totalRefund, $date, $desc, $accepted);
        } else {
            // Cash, Card, UPI
            $this->postEntry($accounts['Cash']->id, 'SaleReturn', $return->id, 'credit', $totalRefund, $date, $desc, $accepted);
        }
    }
}
