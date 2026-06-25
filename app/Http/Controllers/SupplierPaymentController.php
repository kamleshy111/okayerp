<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use Barryvdh\DomPDF\Facade\Pdf;

class SupplierPaymentController extends Controller
{
    public function index(){
        $userId = Auth::id();

        // Query payments with direct database joins to avoid heavy model instantiation
        $paymentsQuery = PurchasePayment::join('suppliers', 'purchase_payments.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.user_id', $userId);
        if (session('private_ledger_unlocked') !== true) {
            $paymentsQuery->where('purchase_payments.accepted', 1);
        }

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
        if (session('private_ledger_unlocked') !== true) {
            $returnsQuery->where('purchases.accepted', 1);
        }

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

        // Merge both arrays and sort by payment_date descending
        $detailedHistory = $payments->concat($returns)
            ->sortByDesc('payment_date')
            ->values()
            ->all();

        return Inertia::render('SupplierPayment/Index',[
            'suppliers' => $detailedHistory,
        ]);
    }

    public function history($id) {
        $userId = Auth::id();
        
        $supplier = Supplier::where('user_id', $userId)->findOrFail($id);

        // Fetch payments for this supplier
        $paymentsQuery = PurchasePayment::where('supplier_id', $id);
        if (session('private_ledger_unlocked') !== true) {
            $paymentsQuery->where('accepted', 1);
        }
        $payments = $paymentsQuery->get()->map(function ($item) {
            return [
                'amount' => (float)$item->amount,
                'payment_date' => $item->payment_date,
                'payment_method' => $item->payment_method,
                'source' => $item->purchase_id ? 'Purchase (Bill #' . $item->purchase_id . ')' : 'Supplier Payment',
            ];
        });

        // Fetch returns for this supplier
        $returnsQuery = PurchaseReturn::whereHas('purchase', function ($q) use ($id) {
            $q->where('supplier_id', $id);
        })->where('user_id', $userId);
        if (session('private_ledger_unlocked') !== true) {
            $returnsQuery->whereHas('purchase', function ($q) {
                $q->where('accepted', 1);
            });
        }
        $returns = $returnsQuery->get()->map(function ($item) {
            $totalRefund = (float)$item->refund_amount + (float)$item->gst_refund_amount;
            return [
                'amount' => -1 * $totalRefund,
                'payment_date' => $item->return_date,
                'payment_method' => 'Refund (' . $item->refund_method . ')' . ($item->return_no ? ' - Return #' . $item->return_no : ''),
                'source' => 'Return (Bill #' . $item->purchase_id . ')',
            ];
        });

        $history = $payments->concat($returns)->sortByDesc('payment_date')->values()->all();

        return Inertia::render('SupplierPayment/History', [
            'supplier' => $supplier,
            'history' => $history,
        ]);
    }

    public function downloadHistoryPdf($id) {
        $userId = Auth::id();
        
        $supplier = Supplier::with('user')->where('user_id', $userId)->findOrFail($id);

        // Fetch payments for this supplier
        $paymentsQuery = PurchasePayment::where('supplier_id', $id);
        if (session('private_ledger_unlocked') !== true) {
            $paymentsQuery->where('accepted', 1);
        }
        $payments = $paymentsQuery->get()->map(function ($item) {
            return [
                'amount' => (float)$item->amount,
                'payment_date' => $item->payment_date,
                'payment_method' => $item->payment_method,
                'source' => $item->purchase_id ? 'Purchase (Bill #' . $item->purchase_id . ')' : 'Supplier Payment',
            ];
        });

        // Fetch returns for this supplier
        $returnsQuery = PurchaseReturn::whereHas('purchase', function ($q) use ($id) {
            $q->where('supplier_id', $id);
        })->where('user_id', $userId);
        if (session('private_ledger_unlocked') !== true) {
            $returnsQuery->whereHas('purchase', function ($q) {
                $q->where('accepted', 1);
            });
        }
        $returns = $returnsQuery->get()->map(function ($item) {
            $totalRefund = (float)$item->refund_amount + (float)$item->gst_refund_amount;
            return [
                'amount' => -1 * $totalRefund,
                'payment_date' => $item->return_date,
                'payment_method' => 'Refund (' . $item->refund_method . ')' . ($item->return_no ? ' - Return #' . $item->return_no : ''),
                'source' => 'Return (Bill #' . $item->purchase_id . ')',
            ];
        });

        $history = $payments->concat($returns)->sortByDesc('payment_date')->values()->all();

        // Calculate summary stats
        $totalPaid = 0.0;
        $totalRefunded = 0.0;
        foreach ($history as $item) {
            if ($item['amount'] > 0) {
                $totalPaid += $item['amount'];
            } else {
                $totalRefunded += abs($item['amount']);
            }
        }
        $netAmount = $totalPaid - $totalRefunded;

        $pdf = Pdf::loadView('supplier_payment_history_pdf', compact('supplier', 'history', 'totalPaid', 'totalRefunded', 'netAmount'))->setPaper('a4');
        return $pdf->stream("payment_history_" . str_replace(' ', '_', strtolower($supplier->name)) . ".pdf");
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
            'accepted' => session('private_ledger_unlocked') === true ? 0 : 1,
        ]);

        $accountingService = new \App\Services\AccountingService($userId);
        $accountingService->postSupplierPayment($purchasePayment);

        return response()->json(['message' => 'Supplier Payments added successfully!']);
    }
}
