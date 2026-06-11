<?php

namespace App\Http\Controllers;

use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleReturnController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $returns = SaleReturn::whereHas('sale.customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with(['sale.customer', 'user'])
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->id,
                'return_no' => $item->return_no,
                'sale_id' => $item->sale_id,
                'customerName' => $item->sale->customer->name ?? '',
                'refund_amount' => $item->refund_amount + $item->gst_refund_amount,
                'refund_method' => $item->refund_method,
                'return_date' => \Carbon\Carbon::parse($item->return_date)->format('d-m-Y'),
                'reason' => $item->reason,
            ];
        });

        return Inertia::render('SaleReturn/Index', [
            'returns' => $returns,
        ]);
    }

    public function create()
    {
        $userId = Auth::id();

        // Get only sales belonging to customers of the logged-in user
        $sales = Sale::whereHas('customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with('customer')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->id,
                'invoice_label' => "Invoice #{$item->id} - " . ($item->customer->name ?? '') . " (Total: ₹" . number_format($item->grand_total, 2) . ")",
            ];
        });

        return Inertia::render('SaleReturn/Create', [
            'sales' => $sales,
        ]);
    }

    public function getSaleDetails($id)
    {
        $userId = Auth::id();

        $sale = Sale::whereHas('customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with(['saleItems.product', 'customer'])
        ->find($id);

        if (!$sale) {
            return response()->json(['message' => 'Sale not found.'], 404);
        }

        // Get previously returned quantities grouped by product
        $previousReturns = SaleReturnItem::whereHas('saleReturn', function ($q) use ($id) {
            $q->where('sale_id', $id);
        })
        ->groupBy('product_id')
        ->select('product_id', DB::raw('SUM(quantity) as total_returned'))
        ->pluck('total_returned', 'product_id')
        ->toArray();

        $items = $sale->saleItems->map(function ($item) use ($previousReturns) {
            $prevQty = $previousReturns[$item->product_id] ?? 0;
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Unknown',
                'price' => $item->price,
                'cgst' => $item->cgst,
                'sgst' => $item->sgst,
                'sold_qty' => $item->quantity,
                'returned_qty' => $prevQty,
                'available_qty' => max(0, $item->quantity - $prevQty),
            ];
        });

        return response()->json([
            'sale_id' => $sale->id,
            'customer_name' => $sale->customer->name ?? '',
            'accepted' => $sale->accepted,
            'due_amount' => max(0, (float)$sale->grand_total - (float)$sale->paid),
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'return_date' => 'required|date',
            'refund_method' => 'required|string',
            'reason' => 'nullable|string',
            'due_deduction' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $userId = Auth::id();

        // Load Sale and verify ownership
        $sale = Sale::whereHas('customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with('saleItems')
        ->find($request->input('sale_id'));

        if (!$sale) {
            return response()->json(['message' => 'Unauthorized or invalid sale.'], 403);
        }

        // Get previously returned quantities
        $previousReturns = SaleReturnItem::whereHas('saleReturn', function ($q) use ($sale) {
            $q->where('sale_id', $sale->id);
        })
        ->groupBy('product_id')
        ->select('product_id', DB::raw('SUM(quantity) as total_returned'))
        ->pluck('total_returned', 'product_id')
        ->toArray();

        DB::beginTransaction();

        try {
            // Generate Return Number RET-XXXXX
            $lastReturn = SaleReturn::whereHas('sale.customer', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->orderBy('id', 'desc')->first();

            $nextNumber = $lastReturn ? ((int) str_replace('RET-', '', $lastReturn->return_no)) + 1 : 1;
            $returnNo = 'RET-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            $refundAmount = 0;
            $gstRefundAmount = 0;

            // Create return record first
            $saleReturn = SaleReturn::create([
                'user_id' => $userId,
                'sale_id' => $sale->id,
                'return_no' => $returnNo,
                'return_date' => $request->input('return_date'),
                'refund_method' => $request->input('refund_method'),
                'reason' => $request->input('reason'),
                'refund_amount' => 0,
                'gst_refund_amount' => 0,
                'due_deduction' => (float)$request->input('due_deduction', 0),
            ]);

            foreach ($request->input('items') as $item) {
                $productId = $item['product_id'];
                $returnQty = (int) $item['quantity'];

                // Check matches in original sale items
                $saleItem = $sale->saleItems->firstWhere('product_id', $productId);
                if (!$saleItem) {
                    throw new \Exception("Product was not part of original sale.");
                }

                $prevReturned = $previousReturns[$productId] ?? 0;
                $maxReturnable = $saleItem->quantity - $prevReturned;

                if ($returnQty > $maxReturnable) {
                    throw new \Exception("Returned quantity for product {$productId} exceeds allowed maximum of {$maxReturnable}.");
                }

                // Calculate base and GST refund values
                $itemBaseRefund = $returnQty * $saleItem->price;
                $itemGstRefund = 0;

                if ($sale->accepted == 1) {
                    $gstRate = ($saleItem->sgst + $saleItem->cgst) / 100;
                    $itemGstRefund = $itemBaseRefund * $gstRate;
                }

                $refundAmount += $itemBaseRefund;
                $gstRefundAmount += $itemGstRefund;

                // Create SaleReturnItem
                SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'product_id' => $productId,
                    'quantity' => $returnQty,
                    'price' => $saleItem->price,
                ]);

                // Increment Product stock
                $product = Product::where('user_id', $userId)->find($productId);
                if ($product) {
                    $product->stock_quantity += $returnQty;
                    $product->save();

                    // Log StockMovement
                    StockMovement::create([
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'quantity' => $returnQty,
                        'type' => 'Addition',
                        'reference_type' => 'SaleReturn',
                        'reference_id' => $saleReturn->id,
                        'reason' => "Sale Return #{$returnNo}",
                    ]);
                }
            }

            // Calculate due reduction vs cash refund based on the sale's original state and request
            $totalRefund = $refundAmount + $gstRefundAmount;
            $dueOnSale = max(0, (float)$sale->grand_total - (float)$sale->paid);
            $requestedDueDeduction = (float)$request->input('due_deduction', 0);
            $dueReduction = min($requestedDueDeduction, min($totalRefund, $dueOnSale));
            $remainingRefund = max(0, $totalRefund - $dueReduction);

            // Update refund totals and actual due deduction
            $saleReturn->update([
                'refund_amount' => $refundAmount,
                'gst_refund_amount' => $gstRefundAmount,
                'due_deduction' => $dueReduction,
            ]);

            // Post to double-entry accounting ledger
            $accountingService = new AccountingService($userId);
            $accountingService->postSaleReturn($saleReturn);

            // Update original Sale record
            $sale->total_amount = max(0, (float)$sale->total_amount - (float)$refundAmount);
            $sale->gst_amount = max(0, (float)$sale->gst_amount - (float)$gstRefundAmount);
            $sale->grand_total = max(0, (float)$sale->grand_total - $totalRefund);
            $sale->paid = max(0, (float)$sale->paid - $remainingRefund);

            if ($sale->paid >= $sale->grand_total) {
                $sale->payment_status = 'Paid';
            } elseif ($sale->paid <= 0) {
                $sale->payment_status = 'Unpaid';
            } else {
                $sale->payment_status = 'Partial';
            }
            $sale->save();

            DB::commit();

            return response()->json([
                'message' => 'Product return successfully recorded.',
                'invoice_url' => route('sale-return.pdf', ['id' => $saleReturn->id]),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to record return: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadReturnPdf($id)
    {
        $userId = Auth::id();

        $return = SaleReturn::whereHas('sale.customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with(['sale.customer.user', 'items.product'])
        ->find($id);

        if (!$return) {
            abort(403, 'Return not found or unauthorized access.');
        }

        $pdf = Pdf::loadView('return_invoice', compact('return'))->setPaper('a4');
        return $pdf->stream("return_invoice_{$return->return_no}.pdf");
    }
}
