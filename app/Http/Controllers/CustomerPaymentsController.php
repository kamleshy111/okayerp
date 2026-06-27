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
            ->where('customers.user_id', $userId)
            ->whereNotIn('sale_payments.payment_method', ['Wallet', 'Advance Deduction']);
        if (session('private_ledger_unlocked') !== true) {
            $paymentsQuery->where('sale_payments.accepted', 1);
        }

        $paymentsRaw = $paymentsQuery->select(
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
        )->get();

        $saleIds = $paymentsRaw->pluck('sale_id')->filter()->unique()->toArray();
        $firstPayments = [];
        if (!empty($saleIds)) {
            $firstPayments = SalePayment::whereIn('sale_id', $saleIds)
                ->selectRaw('sale_id, MIN(id) as first_id')
                ->groupBy('sale_id')
                ->pluck('first_id', 'sale_id')
                ->toArray();
        }

        $payments = $paymentsRaw->map(function ($item) use ($firstPayments) {
            $source = 'Customer Payment';
            if ($item->sale_id) {
                $firstId = $firstPayments[$item->sale_id] ?? null;
                if ($item->transaction_id == $firstId) {
                    $source = 'Sale';
                } else {
                    $source = 'Due Clearance';
                }
            }
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
                'source' => $source,
                'sale_id' => $item->sale_id,
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

        //Merge both arrays and sort by payment_date descending
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
        $paymentsQuery = SalePayment::where('customer_id', $id)
            ->whereNotIn('payment_method', ['Wallet', 'Advance Deduction']);
        if (session('private_ledger_unlocked') !== true) {
            $paymentsQuery->where('accepted', 1);
        }
        $paymentsRaw = $paymentsQuery->get();
        $saleIds = $paymentsRaw->pluck('sale_id')->filter()->unique()->toArray();
        $firstPayments = [];
        if (!empty($saleIds)) {
            $firstPayments = SalePayment::whereIn('sale_id', $saleIds)
                ->selectRaw('sale_id, MIN(id) as first_id')
                ->groupBy('sale_id')
                ->pluck('first_id', 'sale_id')
                ->toArray();
        }

        $payments = $paymentsRaw->map(function ($item) use ($firstPayments) {
            $source = 'Customer Payment';
            if ($item->sale_id) {
                $firstId = $firstPayments[$item->sale_id] ?? null;
                if ($item->id == $firstId) {
                    $source = 'Sale (Invoice #' . $item->sale_id . ')';
                } else {
                    $source = 'Due Clearance (Invoice #' . $item->sale_id . ')';
                }
            }
            return [
                'amount' => (float)$item->amount,
                'payment_date' => $item->payment_date,
                'payment_method' => $item->payment_method,
                'source' => $source,
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
        $paymentsQuery = SalePayment::where('customer_id', $id)
            ->whereNotIn('payment_method', ['Wallet', 'Advance Deduction']);
        if (session('private_ledger_unlocked') !== true) {
            $paymentsQuery->where('accepted', 1);
        }
        $paymentsRaw = $paymentsQuery->get();
        $saleIds = $paymentsRaw->pluck('sale_id')->filter()->unique()->toArray();
        $firstPayments = [];
        if (!empty($saleIds)) {
            $firstPayments = SalePayment::whereIn('sale_id', $saleIds)
                ->selectRaw('sale_id, MIN(id) as first_id')
                ->groupBy('sale_id')
                ->pluck('first_id', 'sale_id')
                ->toArray();
        }

        $payments = $paymentsRaw->map(function ($item) use ($firstPayments) {
            $source = 'Customer Payment';
            if ($item->sale_id) {
                $firstId = $firstPayments[$item->sale_id] ?? null;
                if ($item->id == $firstId) {
                    $source = 'Sale (Invoice #' . $item->sale_id . ')';
                } else {
                    $source = 'Due Clearance (Invoice #' . $item->sale_id . ')';
                }
            }
            return [
                'amount' => (float)$item->amount,
                'payment_date' => $item->payment_date,
                'payment_method' => $item->payment_method,
                'source' => $source,
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
        return Inertia::render('CustomerPayment/Create');
    }

    public function paymentInfo($id) {
        $userId = Auth::id();
        $customer = Customer::where('user_id', $userId)->with('sales')->find($id);

        if (!$customer) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $dueInvoices = [];
        $advanceAmount = 0;

        foreach ($customer->sales as $sale) {
            $paymentsSum = SalePayment::where('sale_id', $sale->id);
            if (session('private_ledger_unlocked') !== true) {
                $paymentsSum->where('accepted', 1);
            }
            $actualPaid = $paymentsSum->sum('amount');
            $saleBalance = $actualPaid - $sale->grand_total;
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

        $lastClosedDate = Auth::user()->last_closed_date;
        if ($lastClosedDate && $request->input('payment_date') <= $lastClosedDate) {
            return response()->json(['message' => 'Cannot create transactions on or before the last closed date (' . $lastClosedDate . ').'], 403);
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
                'payment_method' => 'Wallet',
                'note' => 'Due amount paid from advance balance' . ($request->input('note') ? ' - ' . $request->input('note') : ''),
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

            // Do not update the sale record in the database; it should remain exactly as it was when first created.

            // Post journal entry for advance applied? Usually, advance is unearned revenue,
            // but for simplicity we'll just process the positive payment to clear AR
            $accountingService->postCustomerPayment($advancePayment);
        }

        $salePayment = null;
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

            // Do not update the sale record in the database; it should remain exactly as it was when first created.

            $accountingService->postCustomerPayment($salePayment);
        }

        $receiptUrl = null;
        if ($salePayment) {
            $receiptUrl = route('paymentsCustomer.receipt.show', ['source' => 'payment', 'id' => $salePayment->id]);
        }

        return response()->json([
            'message' => 'Customer Payments added successfully!',
            'receipt_url' => $receiptUrl
        ]);
    }

    private function getPaymentData($source, $id) {
        $userId = Auth::id();
        $payment = null;
        if ($source === 'payment') {
            $paymentQuery = SalePayment::join('customers', 'sale_payments.customer_id', '=', 'customers.id')
                ->where('customers.user_id', $userId)
                ->where('sale_payments.id', $id)
                ->whereNotIn('sale_payments.payment_method', ['Wallet', 'Advance Deduction']);
            if (session('private_ledger_unlocked') !== true) {
                $paymentQuery->where('sale_payments.accepted', 1);
            }
            $payment = $paymentQuery->select('sale_payments.*', 'customers.name as customer_name', 'customers.phone', 'customers.email', 'customers.address', 'customers.gst_number')->firstOrFail();
            $payment->is_return = false;
            $payment->sale_grand_total = null;
            $payment->sale_total_paid = null;
            $payment->sale_remaining = null;

            if ($payment->sale_id) {
                $sale = \App\Models\Sale::find($payment->sale_id);
                if ($sale) {
                    $payment->sale_grand_total = (float)$sale->grand_total;

                    // Sum payments for this sale up to this payment ID
                    $paidQuery = SalePayment::where('sale_id', $payment->sale_id)
                        ->where('id', '<=', $payment->id);
                    if (session('private_ledger_unlocked') !== true) {
                        $paidQuery->where('accepted', 1);
                    }
                    $payment->sale_total_paid = (float)$paidQuery->sum('amount');
                    $payment->sale_remaining = $payment->sale_grand_total - $payment->sale_total_paid;
                }

                // Get first payment ID for this sale to distinguish initial vs due clearance
                $firstPaymentId = SalePayment::where('sale_id', $payment->sale_id)->orderBy('id', 'asc')->value('id');

                // Fetch history of payments for this sale up to this payment ID
                $historyQuery = SalePayment::where('sale_id', $payment->sale_id)
                    ->where('id', '<=', $payment->id)
                    ->orderBy('id', 'asc');
                if (session('private_ledger_unlocked') !== true) {
                    $historyQuery->where('accepted', 1);
                }
                $payment->payment_history = $historyQuery->get()->map(function ($item) use ($firstPaymentId) {
                    $reason = ($item->id == $firstPaymentId)
                        ? "Initial Payment against Invoice #" . $item->sale_id
                        : "Due Clearance for Invoice #" . $item->sale_id;
                    return [
                        'id' => $item->id,
                        'amount' => (float)$item->amount,
                        'payment_date' => $item->payment_date,
                        'payment_method' => $item->payment_method,
                        'note' => $item->note,
                        'is_return' => false,
                        'sale_id' => $item->sale_id,
                        'reason' => $reason,
                        'created_at' => $item->created_at ? $item->created_at->toIso8601String() : null,
                    ];
                });
            } else {
                // Fetch history of direct payments for this customer up to this payment ID
                $historyQuery = SalePayment::where('customer_id', $payment->customer_id)
                    ->whereNull('sale_id')
                    ->where('id', '<=', $payment->id)
                    ->orderBy('id', 'asc');
                if (session('private_ledger_unlocked') !== true) {
                    $historyQuery->where('accepted', 1);
                }
                $payment->payment_history = $historyQuery->get()->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'amount' => (float)$item->amount,
                        'payment_date' => $item->payment_date,
                        'payment_method' => $item->payment_method,
                        'note' => $item->note,
                        'is_return' => false,
                        'sale_id' => null,
                        'reason' => "Advance payment from the customer",
                        'created_at' => $item->created_at ? $item->created_at->toIso8601String() : null,
                    ];
                });
            }
        } else if ($source === 'return') {
            $returnQuery = SaleReturn::join('sales', 'sale_returns.sale_id', '=', 'sales.id')
                ->join('customers', 'sales.customer_id', '=', 'customers.id')
                ->where('sale_returns.user_id', $userId)
                ->where('sale_returns.id', $id);
            if (session('private_ledger_unlocked') !== true) {
                $returnQuery->where('sales.accepted', 1);
            }
            $payment = $returnQuery->select('sale_returns.*', 'customers.name as customer_name', 'customers.phone', 'customers.email', 'customers.address', 'customers.gst_number')->firstOrFail();

            $payment->amount = $payment->refund_amount + $payment->gst_refund_amount;
            $payment->payment_date = $payment->return_date;
            $payment->payment_method = $payment->refund_method;
            $payment->note = $payment->return_no ? 'Return #' . $payment->return_no : 'Refund';
            $payment->is_return = true;
            $payment->sale_grand_total = null;
            $payment->sale_total_paid = null;
            $payment->sale_remaining = null;
            $payment->payment_history = [
                [
                    'id' => $payment->id,
                    'amount' => (float)$payment->amount,
                    'payment_date' => $payment->payment_date,
                    'payment_method' => $payment->payment_method,
                    'note' => $payment->note,
                    'is_return' => true,
                    'sale_id' => $payment->sale_id ?? null,
                    'reason' => "Refund against Return",
                    'created_at' => $payment->created_at ? $payment->created_at->toIso8601String() : null,
                ]
            ];
        } else {
            abort(404);
        }

        // Convert amount to words
        if (!function_exists('convertNumberToWords')) {
            function convertNumberToWords($number) {
                $decimal = round($number - ($no = floor($number)), 2) * 100;
                $hundred = null;
                $digits_length = strlen($no);
                $i = 0;
                $str = array();
                $words = array(
                    0 => '', 1 => 'One', 2 => 'Two',
                    3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
                    7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
                    10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
                    13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
                    16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
                    19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
                    40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
                    70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
                );
                $digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
                while( $i < $digits_length ) {
                    $divider = ($i == 2) ? 10 : 100;
                    $number = floor($no % $divider);
                    $no = floor($no / $divider);
                    $i += $divider == 10 ? 1 : 2;
                    if ($number) {
                        $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                        $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                        $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter].$plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
                    } else $str[] = null;
                }
                $Rupees = implode('', array_reverse($str));
                $paise = ($decimal > 0) ? "and " . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise ' : '';
                return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise . 'Only';
            }
        }
        $payment->amount_in_words = convertNumberToWords($payment->amount);

        return $payment;
    }

    public function showReceipt($source, $id) {
        $payment = $this->getPaymentData($source, $id);

        return Inertia::render('CustomerPayment/Show', [
            'payment' => $payment,
            'source' => $source
        ]);
    }

    public function downloadReceiptPdf($source, $id) {
        $payment = $this->getPaymentData($source, $id);

        $pdf = Pdf::loadView('payment_receipt', compact('payment'))->setPaper('a5', 'landscape');
        return $pdf->stream('payment_receipt_' . $id . '.pdf');
    }
}
