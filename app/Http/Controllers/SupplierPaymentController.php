<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\Purchase;
use Barryvdh\DomPDF\Facade\Pdf;

class SupplierPaymentController extends Controller
{
    public function index(){
        $userId = Auth::id();

        // Query payments with direct database joins to avoid heavy model instantiation
        $paymentsQuery = PurchasePayment::join('suppliers', 'purchase_payments.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.user_id', $userId);

        $payments = $paymentsQuery->select(
            'suppliers.id as supplier_id',
            'suppliers.user_id',
            'suppliers.name',
            'suppliers.email',
            'suppliers.phone',
            'purchase_payments.amount',
            'purchase_payments.payment_date',
            'purchase_payments.payment_method',
            'purchase_payments.purchase_id'
        )->get()->map(function ($item) {
            return [
                'id' => $item->supplier_id,
                'user_id' => $item->user_id,
                'name' => $item->name,
                'email' => $item->email,
                'phone' => $item->phone,
                'amount' => (float)$item->amount,
                'payment_date' => $item->payment_date,
                'payment_method' => $item->payment_method,
                'source' => $item->purchase_id ? 'Purchase' : 'Supplier Payment',
            ];
        });

        // Query purchase returns with direct database joins
        $returnsQuery = PurchaseReturn::join('purchases', 'purchase_returns.purchase_id', '=', 'purchases.id')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('purchase_returns.user_id', $userId);

        $returns = $returnsQuery->select(
            'suppliers.id as supplier_id',
            'suppliers.user_id',
            'suppliers.name',
            'suppliers.email',
            'suppliers.phone',
            'purchase_returns.refund_amount',
            'purchase_returns.gst_refund_amount',
            'purchase_returns.return_date as payment_date',
            'purchase_returns.refund_method',
            'purchase_returns.return_no'
        )->get()->map(function ($item) {
            $totalRefund = (float)$item->refund_amount + (float)$item->gst_refund_amount;
            return [
                'id' => $item->supplier_id,
                'user_id' => $item->user_id,
                'name' => $item->name,
                'email' => $item->email,
                'phone' => $item->phone,
                'amount' => -1 * $totalRefund,
                'payment_date' => $item->payment_date,
                'payment_method' => 'Refund (' . $item->refund_method . ')' . ($item->return_no ? ' - Return #' . $item->return_no : ''),
                'source' => 'Return',
            ];
        });

        // Merge both arrays and sort by created_at descending
        $detailedHistory = $payments->concat($returns)
            ->sortByDesc('created_at') // Sort by created_at descending
            ->values()
            ->all();

        return Inertia::render('SupplierPayment/Index',[
            'suppliers' => $detailedHistory,
        ]);
    }

    public function history($id) {
        $userId = Auth::id();
        $supplier = Supplier::where('user_id', $userId)->findOrFail($id);

        $history = $this->getSupplierLedgerHistory($id, $userId);

        return Inertia::render('SupplierPayment/History', [
            'supplier' => $supplier,
            'history' => $history,
        ]);
    }

    public function downloadHistoryPdf($id) {
        $userId = Auth::id();
        $supplier = Supplier::with('user')->where('user_id', $userId)->findOrFail($id);

        $history = $this->getSupplierLedgerHistory($id, $userId);

        $totalDebits = collect($history)->sum('debit');
        $totalCredits = collect($history)->sum('credit');
        $currentBalance = count($history) > 0 ? $history[0]['running_balance'] : 0.0;

        $pdf = Pdf::loadView('supplier_payment_history_pdf', compact('supplier', 'history', 'totalDebits', 'totalCredits', 'currentBalance'))->setPaper('a4');
        return $pdf->stream("payment_history_" . str_replace(' ', '_', strtolower($supplier->name)) . ".pdf");
    }

    private function getSupplierLedgerHistory($supplierId, $userId)
    {
        $purchases = Purchase::where('supplier_id', $supplierId)
            ->whereHas('supplier', fn($q) => $q->where('user_id', $userId))
            ->get();

        $payments = PurchasePayment::where('supplier_id', $supplierId)->get();

        $returns = PurchaseReturn::whereHas('purchase', fn($q) => $q->where('supplier_id', $supplierId))
            ->where('user_id', $userId)
            ->get();

        $transactions = collect();

        foreach ($purchases as $purchase) {
            $transactions->push([
                'date' => $purchase->purchase_date ?? $purchase->created_at->toDateString(),
                'created_at' => $purchase->created_at ? $purchase->created_at->toDateTimeString() : null,
                'particulars' => "Bill #" . $purchase->id,
                'source' => 'Purchase',
                'debit' => 0.0,
                'credit' => (float)$purchase->grand_total,
                'type' => 'Purchase',
                'ref_id' => $purchase->id,
                'payment_method' => 'Bill',
            ]);
        }

        foreach ($payments as $payment) {
            $particulars = "Payment made" . ($payment->note ? " - " . $payment->note : "");
            $transactions->push([
                'date' => $payment->payment_date ?? $payment->created_at->toDateString(),
                'created_at' => $payment->created_at ? $payment->created_at->toDateTimeString() : null,
                'particulars' => $particulars,
                'source' => $payment->purchase_id ? 'Due Clearance' : 'Supplier Payment',
                'debit' => (float)$payment->amount,
                'credit' => 0.0,
                'type' => 'Payment',
                'ref_id' => $payment->id,
                'purchase_id' => $payment->purchase_id,
                'payment_method' => $payment->payment_method,
            ]);
        }

        foreach ($returns as $return) {
            $totalRefund = (float)$return->refund_amount + (float)$return->gst_refund_amount;
            $transactions->push([
                'date' => $return->return_date ?? $return->created_at->toDateString(),
                'created_at' => $return->created_at ? $return->created_at->toDateTimeString() : null,
                'particulars' => "Purchase Return #" . ($return->return_no ?? $return->id),
                'source' => 'Return',
                'debit' => $totalRefund,
                'credit' => 0.0,
                'type' => 'Return',
                'ref_id' => $return->id,
                'payment_method' => $return->refund_method,
            ]);
        }

        // Sort chronologically (oldest first)
        $sortedArray = $transactions->sort(function ($a, $b) {
            $dateCompare = strcmp($a['date'], $b['date']);
            if ($dateCompare !== 0) {
                return $dateCompare;
            }
            return strcmp($a['created_at'], $b['created_at']);
        })->values()->all();

        // Calculate running balance (Credit - Debit for supplier)
        $runningBalance = 0.0;
        foreach ($sortedArray as &$tx) {
            $runningBalance += ($tx['credit'] - $tx['debit']);
            $tx['running_balance'] = $runningBalance;
        }
        unset($tx);

        // Sort newest first for table presentation
        return array_reverse($sortedArray);
    }

    public function create(){
        return Inertia::render('SupplierPayment/Create');
    }

    public function store(Request $request){

        $validated = $request->validate([
            'supplier_id' => 'required',
            'amount'    => 'required',
            'payment_date'    => 'required',
            'payment_method'    => 'required',
        ], [
            'supplier_id.required' => 'Customer Name is required.',
            'amount.required' => 'Amount is required.',

            'payment_date.required' => 'Date is required.',
            'payment_method.required' => 'Payment Method is required.',
        ]);

        if (!$validated) {
            return response()->json(["message" => $validated]);
        }

        $lastClosedDate = Auth::user()->last_closed_date;
        if ($lastClosedDate && $request->input('payment_date') <= $lastClosedDate) {
            return response()->json(['message' => 'Cannot create transactions on or before the last closed date (' . $lastClosedDate . ').'], 403);
        }

        $userId = Auth::id();
        $supplierExists = Supplier::where('user_id', $userId)->where('id', $request->input('supplier_id'))->exists();
        if (!$supplierExists) {
            return response()->json(['message' => 'Selected supplier is invalid or unauthorized.'], 403);
        }

        // Create a new Purchase Payment
        $purchasePayment = PurchasePayment::create([
            'supplier_id' => $request->input('supplier_id'),
            'amount' => $request->input('amount') ?? '',
            'payment_date' => $request->input('payment_date') ?? 0,
            'payment_method' => $request->input('payment_method') ?? 0,
            'note' => $request->input('note'),
            'accepted' => 1,
        ]);

        $accountingService = new \App\Services\AccountingService($userId);
        $accountingService->postSupplierPayment($purchasePayment);

        return response()->json(['message' => 'Supplier Payments added successfully!']);
    }
}
