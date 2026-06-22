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
            'sale_payments.id as transaction_id',
            'sale_payments.created_at',
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
                'transaction_id' => $item->transaction_id,
                'created_at' => $item->created_at,
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
            'sale_returns.id as transaction_id',
            'sale_returns.created_at',
            'customers.id as customer_id',
            'customers.user_id',
            'customers.name',
            'customers.email',
            'customers.phone',
            'sale_returns.refund_amount',
            'sale_returns.gst_refund_amount',
            'sale_returns.due_deduction',
            'sale_returns.return_date as payment_date',
            'sale_returns.refund_method',
            'sale_returns.return_no'
        )->get()->map(function ($item) {
            $totalRefund = (float)$item->refund_amount + (float)$item->gst_refund_amount + (float)$item->due_deduction;
            return [
                'transaction_id' => $item->transaction_id,
                'created_at' => $item->created_at,
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

        // Merge both arrays and sort by created_at descending
        $detailedHistory = $payments->concat($returns)
            ->sortByDesc('created_at')
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

        $history = $payments->concat($returns)->sortByDesc('created_at')->values()->all();

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

    public function paymentInfo($id) {
        $userId = Auth::id();
        $customer = Customer::where('user_id', $userId)->with(['sales.saleReturns'])->find($id);
        
        if (!$customer) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $dueInvoices = [];
        $advanceAmount = 0;

        foreach ($customer->sales as $sale) {
            $totalDueDeductions = $sale->saleReturns ? $sale->saleReturns->sum('due_deduction') : 0;
            $effectiveGrandTotal = $sale->grand_total - $totalDueDeductions;
            $saleBalance = $sale->paid - $effectiveGrandTotal;
            if ($saleBalance < 0) {
                $dueInvoices[] = [
                    'id' => $sale->id,
                    'invoice_no' => $sale->id,
                    'date' => $sale->created_at->format('Y-m-d'),
                    'grand_total' => $sale->grand_total,
                    'due' => abs($saleBalance)
                ];
            } elseif ($saleBalance > 0) {
                $advanceAmount += $saleBalance;
            }
        }

        $totalDirectPaid = SalePayment::where('customer_id', $id)
                                      ->whereNull('sale_id')
                                      ->sum('amount');
        $advanceAmount += $totalDirectPaid;

        return response()->json([
            'advance_amount' => $advanceAmount,
            'due_invoices' => $dueInvoices
        ]);
    }

    public function store(Request $request){
        $validated = $request->validate([
            'customer_id' => 'required',
            'amount' => 'required_without:use_advance',
            'payment_date' => 'required',
        ], [
            'customer_id.required' => 'Customer Name is required.',
            'amount.required_without' => 'Amount is required if not using advance entirely.',
            'payment_date.required' => 'Date is required.',
        ]);

        if (!$validated) {
            return response()->json(["message" => $validated]);
        }

        $userId = Auth::id();
        $customerExists = Customer::where('user_id', $userId)->where('id', $request->input('customer_id'))->exists();
        if (!$customerExists) {
            return response()->json(['message' => 'Selected customer is invalid or unauthorized.'], 403);
        }

        $saleId = $request->input('sale_id');
        $useAdvance = filter_var($request->input('use_advance'), FILTER_VALIDATE_BOOLEAN);
        $advanceAmountUsed = (float)($request->input('advance_amount_used') ?? 0);
        $cashAmount = (float)($request->input('amount') ?? 0);

        $sale = null;
        if ($saleId) {
            $sale = \App\Models\Sale::where('customer_id', $request->input('customer_id'))->find($saleId);
        }

        $accountingService = new \App\Services\AccountingService($userId);

        // Process Advance Application if any
        if ($useAdvance && $advanceAmountUsed > 0 && $sale) {
            // 1. Positive payment allocated to invoice
            $advancePayment = SalePayment::create([
                'customer_id' => $request->input('customer_id'),
                'sale_id' => $sale->id,
                'amount' => $advanceAmountUsed,
                'payment_date' => $request->input('payment_date'),
                'payment_method' => 'Advance Wallet',
                'note' => 'Paid from Advance Balance' . ($request->input('note') ? ' - ' . $request->input('note') : ''),
                'accepted' => session('private_ledger_unlocked') === true ? 0 : 1,
            ]);
            
            // 2. Negative payment to decrease the overall advance pool
            SalePayment::create([
                'customer_id' => $request->input('customer_id'),
                'sale_id' => null,
                'amount' => -$advanceAmountUsed,
                'payment_date' => $request->input('payment_date'),
                'payment_method' => 'Advance Deduction',
                'note' => 'Applied to Invoice #' . $sale->id,
                'accepted' => session('private_ledger_unlocked') === true ? 0 : 1,
            ]);

            $sale->paid += $advanceAmountUsed;
            if ($sale->paid >= $sale->grand_total) {
                $sale->payment_status = 'Paid';
            } elseif ($sale->paid > 0) {
                $sale->payment_status = 'Partial';
            }
            $sale->save();
            
            // Post journal entry for advance applied? Usually, advance is unearned revenue, 
            // but for simplicity we'll just process the positive payment to clear AR
            $accountingService->postCustomerPayment($advancePayment);
        }

        // Process Standard Cash/Bank Payment if any
        if ($cashAmount > 0) {
            $method = $request->input('payment_method');
            if (empty($method)) {
                return response()->json(['message' => ['payment_method' => ['Payment Method is required for new payments.']]], 422);
            }

            $salePayment = SalePayment::create([
                'customer_id' => $request->input('customer_id'),
                'sale_id' => $saleId ?: null,
                'amount' => $cashAmount,
                'payment_date' => $request->input('payment_date'),
                'payment_method' => $method,
                'note' => $request->input('note'),
                'accepted' => session('private_ledger_unlocked') === true ? 0 : 1,
            ]);

            if ($sale) {
                $sale->paid += $cashAmount;
                if ($sale->paid >= $sale->grand_total) {
                    $sale->payment_status = 'Paid';
                } elseif ($sale->paid > 0) {
                    $sale->payment_status = 'Partial';
                }
                $sale->save();
            }

            $accountingService->postCustomerPayment($salePayment);
        }

        return response()->json(['message' => 'Customer Payments added successfully!']);
    }
}
