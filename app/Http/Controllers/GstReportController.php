<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class GstReportController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Fetch store state for interstate check
        $user = Auth::user();
        $storeState = $user && !empty($user->state) ? trim($user->state) : '';

        // 1. Fetch Sales (Output GST)
        $salesQuery = Sale::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->with(['saleItems.product', 'customer']);

        if (session('private_ledger_unlocked') !== true) {
            $salesQuery->where('accepted', 1);
        }

        if ($startDate) {
            $salesQuery->whereDate('created_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $salesQuery->whereDate('created_at', '<=', Carbon::parse($endDate));
        }

        $sales = $salesQuery->orderBy('created_at', 'desc')->get();

        // 2. Fetch Purchases (Input GST)
        $purchasesQuery = Purchase::whereHas('supplier', fn($q) => $q->where('user_id', $userId))
            ->with(['items.product', 'supplier']);

        if (session('private_ledger_unlocked') !== true) {
            $purchasesQuery->where('accepted', 1);
        }

        if ($startDate) {
            $purchasesQuery->whereDate('purchase_date', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $purchasesQuery->whereDate('purchase_date', '<=', Carbon::parse($endDate));
        }

        $purchases = $purchasesQuery->orderBy('purchase_date', 'desc')->get();

        // 3. Process Sales GST data
        $salesReport = [];
        $totalOutputCgst = 0.0;
        $totalOutputSgst = 0.0;
        $totalOutputIgst = 0.0;
        $totalOutputGst = 0.0;
        $totalSalesTaxable = 0.0;

        foreach ($sales as $sale) {
            $custState = $sale->customer && !empty($sale->customer->state) ? trim($sale->customer->state) : '';
            $isInterstate = $storeState && $custState && (strtolower($storeState) !== strtolower($custState));

            $taxableAmt = 0.0;
            $cgstAmt = 0.0;
            $sgstAmt = 0.0;
            $igstAmt = 0.0;

            foreach ($sale->saleItems as $item) {
                $base = (double)$item->base_price;
                $taxableAmt += $base;

                $itemCgst = (double)$item->cgst;
                $itemSgst = (double)$item->sgst;

                if ($isInterstate) {
                    $igstAmt += $base * ($itemCgst + $itemSgst) / 100;
                } else {
                    $cgstAmt += $base * $itemCgst / 100;
                    $sgstAmt += $base * $itemSgst / 100;
                }
            }

            $totalItemGst = $cgstAmt + $sgstAmt + $igstAmt;

            $salesReport[] = [
                'id' => $sale->id,
                'invoice_no' => $sale->id . '/2026-27',
                'date' => Carbon::parse($sale->created_at)->format('Y-m-d'),
                'customer_name' => $sale->customer->name ?? 'N/A',
                'gstin' => $sale->customer->gst_number ?? 'N/A',
                'taxable_amount' => round($taxableAmt, 2),
                'cgst' => round($cgstAmt, 2),
                'sgst' => round($sgstAmt, 2),
                'igst' => round($igstAmt, 2),
                'total_gst' => round($totalItemGst, 2),
                'grand_total' => round($sale->grand_total, 2),
            ];

            $totalOutputCgst += $cgstAmt;
            $totalOutputSgst += $sgstAmt;
            $totalOutputIgst += $igstAmt;
            $totalOutputGst += $totalItemGst;
            $totalSalesTaxable += $taxableAmt;
        }

        // 4. Process Purchases GST data
        $purchasesReport = [];
        $totalInputCgst = 0.0;
        $totalInputSgst = 0.0;
        $totalInputIgst = 0.0;
        $totalInputGst = 0.0;
        $totalPurchasesTaxable = 0.0;

        $totalRefundableGst = 0.0;
        $totalNonRefundableGst = 0.0;

        foreach ($purchases as $purchase) {
            $suppState = $purchase->supplier && !empty($purchase->supplier->state) ? trim($purchase->supplier->state) : '';
            $isInterstate = $storeState && $suppState && (strtolower($storeState) !== strtolower($suppState));

            $taxableAmt = 0.0;
            $cgstAmt = 0.0;
            $sgstAmt = 0.0;
            $igstAmt = 0.0;

            foreach ($purchase->items as $item) {
                $base = (double)$item->base_price;
                $taxableAmt += $base;

                $itemCgst = (double)$item->cgst;
                $itemSgst = (double)$item->sgst;

                if ($isInterstate) {
                    $igstAmt += $base * ($itemCgst + $itemSgst) / 100;
                } else {
                    $cgstAmt += $base * $itemCgst / 100;
                    $sgstAmt += $base * $itemSgst / 100;
                }
            }

            $totalItemGst = $cgstAmt + $sgstAmt + $igstAmt;
            $isRefundable = (bool)$purchase->is_refundable;

            $purchasesReport[] = [
                'id' => $purchase->id,
                'invoice_no' => $purchase->invoice_no ?? 'N/A',
                'date' => Carbon::parse($purchase->purchase_date)->format('Y-m-d'),
                'supplier_name' => $purchase->supplier->name ?? 'N/A',
                'gstin' => $purchase->supplier->gstin ?? 'N/A',
                'taxable_amount' => round($taxableAmt, 2),
                'cgst' => round($cgstAmt, 2),
                'sgst' => round($sgstAmt, 2),
                'igst' => round($igstAmt, 2),
                'total_gst' => round($totalItemGst, 2),
                'is_refundable' => $isRefundable,
                'grand_total' => round($purchase->grand_total, 2),
            ];

            $totalInputCgst += $cgstAmt;
            $totalInputSgst += $sgstAmt;
            $totalInputIgst += $igstAmt;
            $totalInputGst += $totalItemGst;
            $totalPurchasesTaxable += $taxableAmt;

            if ($isRefundable) {
                $totalRefundableGst += $totalItemGst;
            } else {
                $totalNonRefundableGst += $totalItemGst;
            }
        }

        // 5. Calculate net payable or receivable
        $netTaxAmount = $totalOutputGst - $totalRefundableGst;

        $summary = [
            'total_sales_taxable' => round($totalSalesTaxable, 2),
            'total_output_cgst' => round($totalOutputCgst, 2),
            'total_output_sgst' => round($totalOutputSgst, 2),
            'total_output_igst' => round($totalOutputIgst, 2),
            'total_output_gst' => round($totalOutputGst, 2),

            'total_purchases_taxable' => round($totalPurchasesTaxable, 2),
            'total_input_cgst' => round($totalInputCgst, 2),
            'total_input_sgst' => round($totalInputSgst, 2),
            'total_input_igst' => round($totalInputIgst, 2),
            'total_input_gst' => round($totalInputGst, 2),

            'refundable_input_gst' => round($totalRefundableGst, 2),
            'non_refundable_input_gst' => round($totalNonRefundableGst, 2),

            'net_tax_amount' => round(abs($netTaxAmount), 2),
            'net_status' => $netTaxAmount >= 0 ? 'Payable' : 'Receivable',
        ];

        return Inertia::render('Reports/GstReport', [
            'salesReport' => $salesReport,
            'purchasesReport' => $purchasesReport,
            'summary' => $summary,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }

    public function toggleRefundable($id)
    {
        $userId = Auth::id();
        $purchase = Purchase::whereHas('supplier', fn($q) => $q->where('user_id', $userId))->find($id);

        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found or unauthorized.'], 404);
        }

        $purchase->is_refundable = !$purchase->is_refundable;
        $purchase->save();

        return response()->json([
            'message' => 'Refundable status updated successfully.',
            'is_refundable' => $purchase->is_refundable
        ]);
    }
}
