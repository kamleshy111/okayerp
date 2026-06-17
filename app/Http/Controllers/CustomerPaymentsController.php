<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Customer;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use Barryvdh\DomPDF\Facade\Pdf;

class CustomerPaymentsController extends Controller
{
    public function index(){
        $userId = Auth::id();

        // Query payments with direct database joins to avoid heavy model instantiation
        $paymentsQuery = SalePayment::join('customers', 'sale_payments.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId);
        if (session('private_ledger_unlocked') !== true) {
            $paymentsQuery->where('sale_payments.accepted', 1);
        }

        $payments = $paymentsQuery->select(
            'customers.id as customer_id',
            'customers.user_id',
            'customers.name',
            'customers.email',
            'customers.phone',
            'sale_payments.amount',
            'sale_payments.payment_date',
            'sale_payments.payment_method',
            'sale_payments.sale_id'
        )->get()->map(function ($item) {
            return [
                'id' => $item->customer_id,
                'user_id' => $item->user_id,
                'name' => $item->name,
                'email' => $item->email,
                'phone' => $item->phone,
                'amount' => (float)$item->amount,
                'payment_date' => $item->payment_date,
                'payment_method' => $item->payment_method,
                'source' => $item->sale_id ? 'Sale' : 'Customer Payment',
            ];
        });

        // Query sale returns with direct database joins
        $returnsQuery = SaleReturn::join('sales', 'sale_returns.sale_id', '=', 'sales.id')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sale_returns.user_id', $userId);
        if (session('private_ledger_unlocked') !== true) {
            $returnsQuery->where('sales.accepted', 1);
        }

        $returns = $returnsQuery->select(
            'customers.id as customer_id',
            'customers.user_id',
            'customers.name',
            'customers.email',
            'customers.phone',
            'sale_returns.refund_amount',
            'sale_returns.gst_refund_amount',
            'sale_returns.return_date as payment_date',
            'sale_returns.refund_method',
            'sale_returns.return_no'
        )->get()->map(function ($item) {
            $totalRefund = (float)$item->refund_amount + (float)$item->gst_refund_amount;
            return [
                'id' => $item->customer_id,
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

        return Inertia::render('CustomerPayment/Index',[
            'customers' => $detailedHistory,
        ]);
    }

    public function history($id) {
        $userId = Auth::id();
        
        $customer = Customer::where('user_id', $userId)->findOrFail($id);

        // Fetch payments for this customer
        $paymentsQuery = SalePayment::where('customer_id', $id);
        if (session('private_ledger_unlocked') !== true) {
            $paymentsQuery->where('accepted', 1);
        }
        $payments = $paymentsQuery->get()->map(function ($item) {
            return [
                'amount' => (float)$item->amount,
                'payment_date' => $item->payment_date,
                'payment_method' => $item->payment_method,
                'source' => $item->sale_id ? 'Sale (Invoice #' . $item->sale_id . ')' : 'Customer Payment',
            ];
        });

        // Fetch returns for this customer
        $returnsQuery = SaleReturn::whereHas('sale', function ($q) use ($id) {
            $q->where('customer_id', $id);
        })->where('user_id', $userId);
        if (session('private_ledger_unlocked') !== true) {
            $returnsQuery->whereHas('sale', function ($q) {
                $q->where('accepted', 1);
            });
        }
        $returns = $returnsQuery->get()->map(function ($item) {
            $totalRefund = (float)$item->refund_amount + (float)$item->gst_refund_amount;
            return [
                'amount' => -1 * $totalRefund,
                'payment_date' => $item->return_date,
                'payment_method' => 'Refund (' . $item->refund_method . ')' . ($item->return_no ? ' - Return #' . $item->return_no : ''),
                'source' => 'Return (Invoice #' . $item->sale_id . ')',
            ];
        });

        $history = $payments->concat($returns)->sortByDesc('payment_date')->values()->all();

        return Inertia::render('CustomerPayment/History', [
            'customer' => $customer,
            'history' => $history,
        ]);
    }

    public function downloadHistoryPdf($id) {
        $userId = Auth::id();
        
        $customer = Customer::where('user_id', $userId)->findOrFail($id);

        // Fetch payments for this customer
        $paymentsQuery = SalePayment::where('customer_id', $id);
        if (session('private_ledger_unlocked') !== true) {
            $paymentsQuery->where('accepted', 1);
        }
        $payments = $paymentsQuery->get()->map(function ($item) {
            return [
                'amount' => (float)$item->amount,
                'payment_date' => $item->payment_date,
                'payment_method' => $item->payment_method,
                'source' => $item->sale_id ? 'Sale (Invoice #' . $item->sale_id . ')' : 'Customer Payment',
            ];
        });

        // Fetch returns for this customer
        $returnsQuery = SaleReturn::whereHas('sale', function ($q) use ($id) {
            $q->where('customer_id', $id);
        })->where('user_id', $userId);
        if (session('private_ledger_unlocked') !== true) {
            $returnsQuery->whereHas('sale', function ($q) {
                $q->where('accepted', 1);
            });
        }
        $returns = $returnsQuery->get()->map(function ($item) {
            $totalRefund = (float)$item->refund_amount + (float)$item->gst_refund_amount;
            return [
                'amount' => -1 * $totalRefund,
                'payment_date' => $item->return_date,
                'payment_method' => 'Refund (' . $item->refund_method . ')' . ($item->return_no ? ' - Return #' . $item->return_no : ''),
                'source' => 'Return (Invoice #' . $item->sale_id . ')',
            ];
        });

        $history = $payments->concat($returns)->sortByDesc('payment_date')->values()->all();

        // Calculate summary stats
        $totalReceived = 0.0;
        $totalRefunded = 0.0;
        foreach ($history as $item) {
            if ($item['amount'] > 0) {
                $totalReceived += $item['amount'];
            } else {
                $totalRefunded += abs($item['amount']);
            }
        }
        $netAmount = $totalReceived - $totalRefunded;

        $pdf = Pdf::loadView('customer_payment_history_pdf', compact('customer', 'history', 'totalReceived', 'totalRefunded', 'netAmount'))->setPaper('a4');
        return $pdf->stream("payment_history_" . str_replace(' ', '_', strtolower($customer->name)) . ".pdf");
    }

    public function create(){

        $userId = Auth::id();
  
        $customers = Customer::where('user_id', $userId)->select('id','name')->get();

        return Inertia::render('CustomerPayment/Create',[
            'customers' => $customers
        ]);
    }

    public function store(Request $request){

        $validated = $request->validate([
            'customer_id' => 'required',
            'amount'    => 'required',
            'payment_date'    => 'required',
            'payment_method'    => 'required',
        ], [
            'customer_id.required' => 'Customer Name is required.',
            'amount.required' => 'Amount is required.',

            'payment_date.required' => 'Date is required.',
            'payment_method.required' => 'Payment Method is required.',
        ]);

        if (!$validated) {
            return response()->json(["message" => $validated]);
        }

        $userId = Auth::id();
        $customerExists = Customer::where('user_id', $userId)->where('id', $request->input('customer_id'))->exists();
        if (!$customerExists) {
            return response()->json(['message' => 'Selected customer is invalid or unauthorized.'], 403);
        }

        // Create a new Sale Payment
        $salePayment = SalePayment::create([
            'customer_id' => $request->input('customer_id'),
            'amount' => $request->input('amount') ?? '',
            'payment_date' => $request->input('payment_date') ?? 0,
            'payment_method' => $request->input('payment_method') ?? 0,
            'note' => $request->input('note'),
            'accepted' => session('private_ledger_unlocked') === true ? 0 : 1,
        ]);

        $accountingService = new \App\Services\AccountingService($userId);
        $accountingService->postCustomerPayment($salePayment);

        return response()->json(['message' => 'Customer Payments added successfully!']);
    }
}
