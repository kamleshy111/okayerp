<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $returns = PurchaseReturn::whereHas('purchase.supplier', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with(['purchase.supplier', 'user'])
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->id,
                'return_no' => $item->return_no,
                'purchase_id' => $item->purchase_id,
                'supplierName' => $item->purchase->supplier->name ?? '',
                'refund_amount' => $item->refund_amount + $item->gst_refund_amount,
                'refund_method' => $item->refund_method,
                'return_date' => \Carbon\Carbon::parse($item->return_date)->format('d-m-Y'),
                'reason' => $item->reason,
            ];
        });

        return Inertia::render('PurchaseReturn/Index', [
            'returns' => $returns,
        ]);
    }

    public function create()
    {
        $userId = Auth::id();

        // Get only purchases belonging to suppliers of the logged-in user
        $purchases = Purchase::whereHas('supplier', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with('supplier')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->id,
                'invoice_label' => "Bill #{$item->id} - " . ($item->supplier->name ?? '') . " (Total: ₹" . number_format($item->grand_total, 2) . ")",
            ];
        });

        return Inertia::render('PurchaseReturn/Create', [
            'purchases' => $purchases,
        ]);
    }

    public function getPurchaseDetails($id)
    {
        $userId = Auth::id();

        $purchase = Purchase::whereHas('supplier', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with(['items.product', 'supplier'])
        ->find($id);

        if (!$purchase) {
            return response()->json(['message' => 'Purchase bill not found.'], 404);
        }

        // Get previously returned quantities grouped by product
        $previousReturns = PurchaseReturnItem::whereHas('purchaseReturn', function ($q) use ($id) {
            $q->where('purchase_id', $id);
        })
        ->groupBy('product_id')
        ->select('product_id', DB::raw('SUM(quantity) as total_returned'))
        ->pluck('total_returned', 'product_id')
        ->toArray();

        $items = $purchase->items->map(function ($item) use ($previousReturns) {
            $prevQty = $previousReturns[$item->product_id] ?? 0;
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Unknown',
                'price' => $item->price,
                'cgst' => $item->cgst,
                'sgst' => $item->sgst,
                'purchased_qty' => $item->quantity,
                'returned_qty' => $prevQty,
                'available_qty' => max(0, $item->quantity - $prevQty),
            ];
        });

        $supplierTotalDue = 0.0;
        if ($purchase->supplier) {
            $dueAmount = 0;
            $advanceAmount = 0;

            $supplierPurchases = Purchase::where('supplier_id', $purchase->supplier->id)->get();
            foreach ($supplierPurchases as $p) {
                $paymentsSum = \App\Models\PurchasePayment::where('purchase_id', $p->id);
                $actualPaid = $paymentsSum->sum('amount');

                $dueDeductionsSum = (float)\App\Models\PurchaseReturnItem::where('purchase_id', $p->id)->sum('due_deduction');

                $storeCreditRefundsSum = (float)\App\Models\PurchaseReturn::where('purchase_id', $p->id)
                    ->where('refund_method', 'Store Credit')
                    ->get()
                    ->sum(fn($r) => (float)$r->refund_amount + (float)$r->gst_refund_amount);

                $purchaseBalance = $actualPaid - (float)$p->grand_total + $dueDeductionsSum;
                if ($purchaseBalance < 0) {
                    $dueAmount += abs($purchaseBalance);
                } elseif ($purchaseBalance > 0) {
                    $advanceAmount += $purchaseBalance;
                }

                $advanceAmount += $storeCreditRefundsSum;
            }

            $totalDirectPaid = \App\Models\PurchasePayment::where('supplier_id', $purchase->supplier->id)
                ->whereNull('purchase_id');
            $advanceAmount += $totalDirectPaid->sum('amount');

            $supplierTotalDue = max(0, $dueAmount - $advanceAmount);
        }

        return response()->json([
            'purchase_id' => $purchase->id,
            'supplier_name' => $purchase->supplier->name ?? '',
            'accepted' => $purchase->accepted,
            'due_amount' => max(0, (float)$purchase->grand_total - (float)$purchase->paid),
            'supplier_total_due' => round($supplierTotalDue, 2),
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'return_date' => 'required|date',
            'refund_method' => 'required|string',
            'reason' => 'nullable|string',
            'due_deduction' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $userId = Auth::id();

        // Load Purchase and verify ownership
        $purchase = Purchase::whereHas('supplier', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with('items')
        ->find($request->input('purchase_id'));

        if (!$purchase) {
            return response()->json(['message' => 'Unauthorized or invalid purchase.'], 403);
        }

        // Get previously returned quantities
        $previousReturns = PurchaseReturnItem::whereHas('purchaseReturn', function ($q) use ($purchase) {
            $q->where('purchase_id', $purchase->id);
        })
        ->groupBy('product_id')
        ->select('product_id', DB::raw('SUM(quantity) as total_returned'))
        ->pluck('total_returned', 'product_id')
        ->toArray();

        DB::beginTransaction();

        try {
            // Generate Return Number PRET-XXXXX
            $lastReturn = PurchaseReturn::whereHas('purchase.supplier', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->orderBy('id', 'desc')->first();

            if ($lastReturn) {
                $parts = explode('-', $lastReturn->return_no);
                $nextNumber = ((int) end($parts)) + 1;
            } else {
                $nextNumber = 1;
            }
            $returnNo = 'PRET-' . $userId . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            $refundAmount = 0;
            $gstRefundAmount = 0;

            // Generate unique return number
            $lastReturn = PurchaseReturn::where('user_id', $userId)->orderBy('id', 'desc')->first();
            if ($lastReturn) {
                $parts = explode('-', $lastReturn->return_no);
                $nextNumber = ((int) end($parts)) + 1;
            } else {
                $nextNumber = 1;
            }
            $returnNo = 'PRET-' . $userId . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            // Create return record first
            $purchaseReturn = PurchaseReturn::create([
                'user_id' => $userId,
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'return_no' => $returnNo,
                'return_date' => $request->input('return_date'),
                'refund_method' => $request->input('refund_method'),
                'reason' => $request->input('reason'),
                'refund_amount' => 0,
                'gst_refund_amount' => 0,
                'due_deduction' => 0,
            ]);

            $itemsToSave = [];
            foreach ($request->input('items') as $item) {
                $productId = $item['product_id'];
                $returnQty = (int) $item['quantity'];

                $purchaseItem = $purchase->items->firstWhere('product_id', $productId);
                if (!$purchaseItem) {
                    throw new \Exception("Product was not part of original purchase.");
                }

                $prevReturned = $previousReturns[$productId] ?? 0;
                $maxReturnable = $purchaseItem->quantity - $prevReturned;

                if ($returnQty > $maxReturnable) {
                    throw new \Exception("Returned quantity for product {$productId} exceeds allowed maximum of {$maxReturnable}.");
                }

                $itemBaseRefund = $returnQty * $purchaseItem->price;
                $itemGstRefund = 0;
                if ($purchase->accepted == 1) {
                    $gstRate = ($purchaseItem->sgst + $purchaseItem->cgst) / 100;
                    $itemGstRefund = $itemBaseRefund * $gstRate;
                }

                $refundAmount += $itemBaseRefund;
                $gstRefundAmount += $itemGstRefund;

                $itemsToSave[] = [
                    'product_id' => $productId,
                    'quantity' => $returnQty,
                    'price' => $purchaseItem->price,
                    'refund_total' => $itemBaseRefund + $itemGstRefund,
                    'purchase_id' => $purchase->id,
                    'due_deduction' => 0.00,
                ];

                // Decrement Product stock
                $product = Product::where('user_id', $userId)->find($productId);
                if ($product) {
                    $product->stock_quantity -= $returnQty;
                    $product->save();

                    // Log StockMovement
                    StockMovement::create([
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'quantity' => $returnQty,
                        'type' => 'Deduction',
                        'reference_type' => 'PurchaseReturn',
                        'reference_id' => $purchaseReturn->id,
                        'reason' => "Purchase Return #{$returnNo}",
                    ]);
                }
            }

            // Calculate supplier-level due deductions
            $totalRefund = $refundAmount + $gstRefundAmount;
            $requestedDueDeduction = (float)$request->input('due_deduction', 0);
            $remainingDeduction = min($requestedDueDeduction, $totalRefund);
            $deductionsByPurchase = [];

            // Find all purchases for this supplier to greedily allocate the due deduction
            $supplierPurchases = Purchase::where('supplier_id', $purchase->supplier_id)
                ->orderBy('purchase_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($supplierPurchases as $sp) {
                if ($remainingDeduction <= 0) {
                    break;
                }

                $paymentsSum = \App\Models\PurchasePayment::where('purchase_id', $sp->id);
                $actualPaid = $paymentsSum->sum('amount');

                $prevDeductions = \App\Models\PurchaseReturnItem::where('purchase_id', $sp->id)->sum('due_deduction');
                $dueOnSp = max(0, (float)$sp->grand_total - $actualPaid - $prevDeductions);

                if ($dueOnSp > 0) {
                    $allocated = min($remainingDeduction, $dueOnSp);
                    $deductionsByPurchase[$sp->id] = $allocated;
                    $remainingDeduction -= $allocated;
                }
            }

            $actualDueDeduction = $requestedDueDeduction - $remainingDeduction;

            // Save items without splitting
            $finalItemsToSave = [];

            $remainingDueDeduction = $actualDueDeduction;

            foreach ($itemsToSave as $index => $itemInfo) {

                if ($index == 0) {
                    $itemInfo['due_deduction'] = $remainingDueDeduction;
                } else {
                    $itemInfo['due_deduction'] = 0;
                }

                $finalItemsToSave[] = $itemInfo;
            }

            // Save items to database
            foreach ($finalItemsToSave as $itemData) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_id' => $itemData['product_id'],
                    'purchase_id' => $itemData['purchase_id'],
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'due_deduction' => $itemData['due_deduction'],
                ]);
            }

            // Update return totals
            $purchaseReturn->update([
                'refund_amount' => $refundAmount,
                'gst_refund_amount' => $gstRefundAmount,
                'due_deduction' => $actualDueDeduction,
            ]);

            // Post to double-entry accounting ledger
            $accountingService = new AccountingService($userId);
            $accountingService->postPurchaseReturn($purchaseReturn);

            // Update payment status for all affected purchases
            foreach ($supplierPurchases as $sp) {
                $totalDueDeductions = \App\Models\PurchaseReturnItem::where('purchase_id', $sp->id)->sum('due_deduction');
                $effectiveBalance = max(0, (float)$sp->grand_total - (float)$sp->paid - $totalDueDeductions);

                if ($effectiveBalance <= 0) {
                    $sp->payment_status = 'Paid';
                } elseif ((float)$sp->paid + $totalDueDeductions <= 0) {
                    $sp->payment_status = 'Unpaid';
                } else {
                    $sp->payment_status = 'Partial';
                }
                $sp->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Supplier return successfully recorded.',
                'invoice_url' => route('purchase-return.pdf', ['id' => $purchaseReturn->id]),
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

        $return = PurchaseReturn::whereHas('purchase.supplier', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with(['purchase.supplier.user', 'items.product'])
        ->find($id);

        if (!$return) {
            abort(403, 'Return not found or unauthorized access.');
        }

        $pdf = Pdf::loadView('purchase_return_invoice', compact('return'))->setPaper('a4');
        return $pdf->stream("purchase_return_invoice_{$return->return_no}.pdf");
    }

    public function destroy($id)
    {
        $userId = Auth::id();
        $purchaseReturn = PurchaseReturn::where('user_id', $userId)->with('items')->findOrFail($id);

        DB::beginTransaction();
        try {
            // Find all involved purchase IDs to recalculate payment statuses later
            $purchaseIds = $purchaseReturn->items->pluck('purchase_id')->unique()->filter()->toArray();

            // Reverse stock updates and remove movements
            foreach ($purchaseReturn->items as $item) {
                $product = Product::where('user_id', $userId)->find($item->product_id);
                if ($product) {
                    $product->stock_quantity += $item->quantity; // since it was returned (decreased), deleting return means increasing it back
                    $product->save();
                }

                // Delete StockMovement
                StockMovement::where('user_id', $userId)
                    ->where('product_id', $item->product_id)
                    ->where('reference_type', 'PurchaseReturn')
                    ->where('reference_id', $purchaseReturn->id)
                    ->delete();
            }

            // Reverse ledger entries
            $accountingService = new AccountingService($userId);
            $accountingService->clearEntries('PurchaseReturn', $purchaseReturn->id);

            // Delete return items and return record
            $purchaseReturn->items()->delete();
            $purchaseReturn->delete();

            // Update payment status for each involved purchase
            if (!empty($purchaseIds)) {
                $purchases = \App\Models\Purchase::whereIn('id', $purchaseIds)->get();
                foreach ($purchases as $sp) {
                    $totalDueDeductions = \App\Models\PurchaseReturnItem::where('purchase_id', $sp->id)->sum('due_deduction');
                    $effectiveBalance = max(0, (float)$sp->grand_total - (float)$sp->paid - $totalDueDeductions);

                    if ($effectiveBalance <= 0) {
                        $sp->payment_status = 'Paid';
                    } elseif ((float)$sp->paid + $totalDueDeductions <= 0) {
                        $sp->payment_status = 'Unpaid';
                    } else {
                        $sp->payment_status = 'Partial';
                    }
                    $sp->save();
                }
            }

            DB::commit();
            return response()->json(['message' => 'Purchase return deleted successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete purchase return: ' . $e->getMessage()], 500);
        }
    }
}
