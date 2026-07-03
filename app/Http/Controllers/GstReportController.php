<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\SaleReturn;
use App\Models\PurchaseReturn;
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
            ->where('accepted', 1)
            ->with(['saleItems.product', 'customer']);

        if ($startDate) {
            $salesQuery->whereDate('created_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $salesQuery->whereDate('created_at', '<=', Carbon::parse($endDate));
        }

        $sales = $salesQuery->orderBy('created_at', 'desc')->get();

        // 1b. Fetch Sales Returns
        $saleReturnsQuery = SaleReturn::where('user_id', $userId)
            ->whereHas('sale', function($q) {
                $q->where('accepted', 1);
            })
            ->with(['sale.saleItems', 'sale.customer', 'items']);

        if ($startDate) {
            $saleReturnsQuery->whereDate('return_date', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $saleReturnsQuery->whereDate('return_date', '<=', Carbon::parse($endDate));
        }

        $saleReturns = $saleReturnsQuery->orderBy('return_date', 'desc')->get();

        // 2. Fetch Purchases (Input GST)
        $purchasesQuery = Purchase::whereHas('supplier', fn($q) => $q->where('user_id', $userId))
            ->where('accepted', 1)
            ->with(['items.product', 'supplier']);

        if ($startDate) {
            $purchasesQuery->whereDate('purchase_date', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $purchasesQuery->whereDate('purchase_date', '<=', Carbon::parse($endDate));
        }

        $purchases = $purchasesQuery->orderBy('purchase_date', 'desc')->get();

        // 2b. Fetch Purchase Returns
        $purchaseReturnsQuery = PurchaseReturn::where('user_id', $userId)
            ->whereHas('purchase', function($q) {
                $q->where('accepted', 1);
            })
            ->with(['purchase.items', 'purchase.supplier', 'items']);

        if ($startDate) {
            $purchaseReturnsQuery->whereDate('return_date', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $purchaseReturnsQuery->whereDate('return_date', '<=', Carbon::parse($endDate));
        }

        $purchaseReturns = $purchaseReturnsQuery->orderBy('return_date', 'desc')->get();

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
                'created_at' => $sale->created_at,
            ];

            $totalOutputCgst += $cgstAmt;
            $totalOutputSgst += $sgstAmt;
            $totalOutputIgst += $igstAmt;
            $totalOutputGst += $totalItemGst;
            $totalSalesTaxable += $taxableAmt;
        }

        foreach ($saleReturns as $return) {
            $customer = $return->customer;
            if (!$customer) {
                $customer = $return->sale ? $return->sale->customer : null;
            }
            if (!$customer) continue;

            $custState = $customer->state && !empty($customer->state) ? trim($customer->state) : '';
            $isInterstate = $storeState && $custState && (strtolower($storeState) !== strtolower($custState));

            $taxableAmt = 0.0;
            $cgstAmt = 0.0;
            $sgstAmt = 0.0;
            $igstAmt = 0.0;

            foreach ($return->items as $item) {
                $base = (double)$item->quantity * (double)$item->price;
                $taxableAmt += $base;

                $itemSale = $item->sale;
                if (!$itemSale) {
                    $itemSale = $return->sale;
                }

                $saleItem = null;
                if ($itemSale) {
                    $saleItem = \App\Models\SaleItem::where('sale_id', $itemSale->id)
                        ->where('product_id', $item->product_id)
                        ->first();
                }

                $itemCgst = $saleItem ? (double)$saleItem->cgst : 0.0;
                $itemSgst = $saleItem ? (double)$saleItem->sgst : 0.0;

                if ($isInterstate) {
                    $igstAmt += $base * ($itemCgst + $itemSgst) / 100;
                } else {
                    $cgstAmt += $base * $itemCgst / 100;
                    $sgstAmt += $base * $itemSgst / 100;
                }
            }

            $totalItemGst = $cgstAmt + $sgstAmt + $igstAmt;

            $salesReport[] = [
                'id' => 'return_' . $return->id,
                'invoice_no' => $return->return_no,
                'date' => Carbon::parse($return->return_date)->format('Y-m-d'),
                'customer_name' => $customer->name ?? 'N/A',
                'gstin' => $customer->gst_number ?? 'N/A',
                'taxable_amount' => -round($taxableAmt, 2),
                'cgst' => -round($cgstAmt, 2),
                'sgst' => -round($sgstAmt, 2),
                'igst' => -round($igstAmt, 2),
                'total_gst' => -round($totalItemGst, 2),
                'grand_total' => -round($taxableAmt + $totalItemGst, 2),
                'is_return' => true,
                'created_at' => $return->created_at,
            ];

            $totalOutputCgst -= $cgstAmt;
            $totalOutputSgst -= $sgstAmt;
            $totalOutputIgst -= $igstAmt;
            $totalOutputGst -= $totalItemGst;
            $totalSalesTaxable -= $taxableAmt;
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
                'created_at' => $purchase->created_at,
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

        foreach ($purchaseReturns as $return) {
            $purchase = $return->purchase;
            if (!$purchase) continue;

            $suppState = $purchase->supplier && !empty($purchase->supplier->state) ? trim($purchase->supplier->state) : '';
            $isInterstate = $storeState && $suppState && (strtolower($storeState) !== strtolower($suppState));

            $taxableAmt = 0.0;
            $cgstAmt = 0.0;
            $sgstAmt = 0.0;
            $igstAmt = 0.0;

            foreach ($return->items as $item) {
                $base = (double)$item->quantity * (double)$item->price;
                $taxableAmt += $base;

                $purchaseItem = $purchase->items->firstWhere('product_id', $item->product_id);
                $itemCgst = $purchaseItem ? (double)$purchaseItem->cgst : 0.0;
                $itemSgst = $purchaseItem ? (double)$purchaseItem->sgst : 0.0;

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
                'id' => 'return_' . $return->id,
                'invoice_no' => $return->return_no,
                'date' => Carbon::parse($return->return_date)->format('Y-m-d'),
                'supplier_name' => $purchase->supplier->name ?? 'N/A',
                'gstin' => $purchase->supplier->gstin ?? 'N/A',
                'taxable_amount' => -round($taxableAmt, 2),
                'cgst' => -round($cgstAmt, 2),
                'sgst' => -round($sgstAmt, 2),
                'igst' => -round($igstAmt, 2),
                'total_gst' => -round($totalItemGst, 2),
                'is_refundable' => $isRefundable,
                'grand_total' => -round($taxableAmt + $totalItemGst, 2),
                'is_return' => true,
                'created_at' => $return->created_at,
            ];

            $totalInputCgst -= $cgstAmt;
            $totalInputSgst -= $sgstAmt;
            $totalInputIgst -= $igstAmt;
            $totalInputGst -= $totalItemGst;
            $totalPurchasesTaxable -= $taxableAmt;

            if ($isRefundable) {
                $totalRefundableGst -= $totalItemGst;
            } else {
                $totalNonRefundableGst -= $totalItemGst;
            }
        }

        // Sort reports by created_at desc
        usort($salesReport, function ($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });

        usort($purchasesReport, function ($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });

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
