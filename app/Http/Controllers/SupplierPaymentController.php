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
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class SupplierPaymentController extends Controller
{
    public function index(){
        $userId = Auth::id();

        // Query payments with direct database joins to avoid heavy model instantiation
        $paymentsQuery = PurchasePayment::join('suppliers', 'purchase_payments.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.user_id', $userId);

        $payments = $paymentsQuery->select(
            'purchase_payments.id as transaction_id',
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
                'transaction_id' => $item->transaction_id,
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
        $productHistory = $this->getSupplierProductHistory($id, $userId);

        $purchasedProducts = collect($productHistory)
            ->map(function ($item) {
                return [
                    'id' => $item['product_id'],
                    'name' => $item['product_name'],
                    'code' => $item['product_code'],
                ];
            })
            ->unique('id')
            ->values()
            ->all();

        return Inertia::render('SupplierPayment/History', [
            'supplier' => $supplier,
            'history' => $history,
            'productHistory' => $productHistory,
            'purchasedProducts' => $purchasedProducts,
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

    public function downloadProductReportPdf(Request $request, $id) {
        $supplier = Supplier::with('user')->findOrFail($id);
        $userId = $supplier->user_id;

        $rawProductHistory = $this->getSupplierProductHistory($id, $userId);

        $invoiceSearch = trim($request->get('invoice', ''));
        $productSearch = trim($request->get('product', ''));
        $fromDate = $request->get('from');
        $toDate = $request->get('to');

        $filtered = collect($rawProductHistory)->filter(function ($item) use ($invoiceSearch, $productSearch, $fromDate, $toDate) {
            if ($invoiceSearch !== '') {
                if (!isset($item['invoice_no']) || !str_contains(strtolower($item['invoice_no']), strtolower($invoiceSearch))) {
                    return false;
                }
            }
            if ($productSearch !== '') {
                $matchName = isset($item['product_name']) && str_contains(strtolower($item['product_name']), strtolower($productSearch));
                $matchCode = isset($item['product_code']) && str_contains(strtolower($item['product_code']), strtolower($productSearch));
                if (!$matchName && !$matchCode) {
                    return false;
                }
            }
            if ($fromDate && isset($item['date']) && $item['date'] < $fromDate) {
                return false;
            }
            if ($toDate && isset($item['date']) && $item['date'] > $toDate) {
                return false;
            }
            return true;
        });

        // Group/aggregate by product_id
        $aggregated = $filtered->groupBy('product_id')->map(function ($items) {
            $first = $items->first();
            $totalQty = $items->sum('quantity');
            $totalSubtotal = $items->sum('subtotal');
            $totalAmount = $items->sum('total_amount');
            $rates = $items->pluck('price')->unique()->filter()->values()->all();

            $rateDisplay = '₹0.00';
            if (count($rates) === 1) {
                $rateDisplay = '₹' . number_format($rates[0], 2);
            } elseif (count($rates) > 1) {
                $rateDisplay = '₹' . number_format(min($rates), 2) . ' - ₹' . number_format(max($rates), 2);
            } elseif ($totalQty != 0) {
                $rateDisplay = '₹' . number_format(abs($totalSubtotal / $totalQty), 2);
            }

            return [
                'product_id' => $first['product_id'],
                'product_name' => $first['product_name'],
                'product_code' => $first['product_code'],
                'unit_type' => $first['unit_type'] ?? 'Pcs',
                'total_quantity' => $totalQty,
                'rate_display' => $rateDisplay,
                'total_amount' => $totalAmount,
                'transactions' => $items->values()->all(),
            ];
        })->values();

        $totalProductsCount = $aggregated->count();
        $grandTotalQuantity = $aggregated->sum('total_quantity');
        $grandTotalAmount = $aggregated->sum('total_amount');

        $pdf = Pdf::loadView('supplier_product_report_pdf', compact('supplier', 'aggregated', 'totalProductsCount', 'grandTotalQuantity', 'grandTotalAmount', 'fromDate', 'toDate'))->setPaper('a4');
        return $pdf->stream("supplier_product_report_" . str_replace(' ', '_', strtolower($supplier->name)) . ".pdf");
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

    private function getSupplierProductHistory($supplierId, $userId)
    {
        $purchaseItems = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->where('purchases.supplier_id', $supplierId)
            ->where('suppliers.user_id', $userId)
            ->select(
                'purchase_items.id',
                'purchases.id as purchase_id',
                'purchases.invoice_no',
                'purchases.purchase_date',
                'purchases.created_at',
                'products.id as product_id',
                'products.name as product_name',
                'products.sku as product_code',
                'purchase_items.quantity',
                'purchase_items.unit_type',
                'purchase_items.price',
                'purchase_items.base_price',
                'purchase_items.sgst',
                'purchase_items.cgst',
                'purchase_items.description'
            )
            ->get();

        $purchaseReturnItems = DB::table('purchase_return_items')
            ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
            ->join('purchases', 'purchase_return_items.purchase_id', '=', 'purchases.id')
            ->join('products', 'purchase_return_items.product_id', '=', 'products.id')
            ->where('purchases.supplier_id', $supplierId)
            ->where('purchase_returns.user_id', $userId)
            ->select(
                'purchase_return_items.id',
                'purchases.id as purchase_id',
                'purchase_returns.id as purchase_return_id',
                'purchase_returns.return_no',
                'purchase_returns.return_date',
                'purchase_returns.created_at',
                'products.id as product_id',
                'products.name as product_name',
                'products.sku as product_code',
                'purchase_return_items.quantity',
                'purchase_return_items.price'
            )
            ->get();

        $items = collect();

        foreach ($purchaseItems as $item) {
            $qty = (float)$item->quantity;
            $price = (float)$item->price;
            $subtotal = $qty * $price;
            $gstRate = (float)($item->sgst ?? 0) + (float)($item->cgst ?? 0);
            $gstAmount = $subtotal * ($gstRate / 100);
            $totalAmount = $subtotal + $gstAmount;

            $items->push([
                'id' => 'purchase_' . $item->id,
                'ref_id' => $item->purchase_id,
                'type' => 'Purchase',
                'date' => $item->purchase_date ?? ($item->created_at ? substr($item->created_at, 0, 10) : null),
                'created_at' => $item->created_at,
                'invoice_no' => $item->invoice_no ? $item->invoice_no : ('Bill #' . $item->purchase_id),
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_code' => $item->product_code,
                'quantity' => $qty,
                'unit_type' => $item->unit_type ?? 'Pcs',
                'price' => $price,
                'gst_rate' => $gstRate,
                'gst_amount' => $gstAmount,
                'subtotal' => $subtotal,
                'total_amount' => $totalAmount,
                'description' => $item->description,
            ]);
        }

        foreach ($purchaseReturnItems as $item) {
            $qty = (float)$item->quantity;
            $price = (float)$item->price;
            $totalAmount = $qty * $price;

            $items->push([
                'id' => 'return_' . $item->id,
                'ref_id' => $item->purchase_id,
                'type' => 'Return',
                'date' => $item->return_date ?? ($item->created_at ? substr($item->created_at, 0, 10) : null),
                'created_at' => $item->created_at,
                'invoice_no' => 'Return #' . ($item->return_no ?? $item->purchase_return_id),
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_code' => $item->product_code,
                'quantity' => -1 * $qty,
                'unit_type' => 'Pcs',
                'price' => $price,
                'gst_rate' => 0,
                'gst_amount' => 0,
                'subtotal' => -1 * $totalAmount,
                'total_amount' => -1 * $totalAmount,
                'description' => 'Purchase Return',
            ]);
        }

        return $items->sort(function ($a, $b) {
            $dateCompare = strcmp($b['date'] ?? '', $a['date'] ?? '');
            if ($dateCompare !== 0) {
                return $dateCompare;
            }
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        })->values()->all();
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
        // Dispatch automatic Supplier Payment Paid notification
        try {
            $supplier = Supplier::find($request->input('supplier_id'));
            (new \App\Services\NotificationService())->dispatch(
                Auth::user(),
                'supplier_payment',
                [
                    'supplier_name' => $supplier ? $supplier->name : 'Supplier',
                    'amount' => number_format($request->input('amount'), 2),
                    'payment_method' => $request->input('payment_method') ?: 'Payment',
                    'date' => $request->input('payment_date') ?: now()->toDateString(),
                    'business_name' => Auth::user()->name ?: 'OkayERP',
                    'pdf_url' => '#',
                ],
                $supplier ? $supplier->phone : null,
                $supplier ? $supplier->email : null,
                "/paymentSupplier"
            );
        } catch (\Exception $ne) {
            \Illuminate\Support\Facades\Log::error('Supplier payment notification dispatch failed: ' . $ne->getMessage());
        }

        return response()->json(['message' => 'Supplier Payments added successfully!']);
    }

    public function destroy($id)
    {
        $userId = Auth::id();
        $payment = PurchasePayment::whereHas('supplier', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment record not found.'], 404);
        }

        $setting = \App\Models\NotificationSetting::where('user_id', $userId)->first();
        if ($setting && !$setting->allow_purchase_delete) {
            return response()->json(['message' => 'Supplier payment deletion is disabled in your store settings.'], 403);
        }

        $supplierId = $payment->supplier_id;

        DB::beginTransaction();
        try {
            // Clear Ledger entries
            $accountingService = new \App\Services\AccountingService($userId);
            $accountingService->clearEntries('PurchasePayment', $payment->id);

            // Delete payment record
            $payment->delete();

            // Re-align and rearrange all supplier purchases FIFO
            $purchases = Purchase::where('supplier_id', $supplierId)
                ->orderBy('created_at', 'asc')
                ->get();

            $totalPayments = PurchasePayment::where('supplier_id', $supplierId)
                ->whereNotIn('payment_method', ['Wallet', 'Advance Deduction'])
                ->sum('amount');

            foreach ($purchases as $p) {
                $returnDueDeduction = \App\Models\PurchaseReturnItem::where('purchase_id', $p->id)->sum('due_deduction');
                $netBill = (float)$p->grand_total - (float)$returnDueDeduction;

                $allocated = 0.0;
                if ($totalPayments > 0 && $netBill > 0) {
                    if ($totalPayments >= $netBill) {
                        $allocated = $netBill;
                        $totalPayments -= $netBill;
                    } else {
                        $allocated = $totalPayments;
                        $totalPayments = 0.0;
                    }
                }

                $p->paid = round($allocated, 2);

                if ($p->paid + $returnDueDeduction >= (float)$p->grand_total) {
                    $p->payment_status = 'Paid';
                } elseif ($p->paid <= 0) {
                    $p->payment_status = 'Unpaid';
                } else {
                    $p->payment_status = 'Partial';
                }
                $p->save();
            }

            DB::commit();
            return response()->json(['message' => 'Payment record deleted and supplier bills rearranged successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete payment record: ' . $e->getMessage()], 500);
        }
    }
}
