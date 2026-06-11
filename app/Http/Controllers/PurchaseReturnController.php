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

        return response()->json([
            'purchase_id' => $purchase->id,
            'supplier_name' => $purchase->supplier->name ?? '',
            'accepted' => $purchase->accepted,
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

            $nextNumber = $lastReturn ? ((int) str_replace('PRET-', '', $lastReturn->return_no)) + 1 : 1;
            $returnNo = 'PRET-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            $refundAmount = 0;
            $gstRefundAmount = 0;

            // Create return record first
            $purchaseReturn = PurchaseReturn::create([
                'user_id' => $userId,
                'purchase_id' => $purchase->id,
                'return_no' => $returnNo,
                'return_date' => $request->input('return_date'),
                'refund_method' => $request->input('refund_method'),
                'reason' => $request->input('reason'),
                'refund_amount' => 0,
                'gst_refund_amount' => 0,
            ]);

            foreach ($request->input('items') as $item) {
                $productId = $item['product_id'];
                $returnQty = (int) $item['quantity'];

                // Check matches in original items
                $purchaseItem = $purchase->items->firstWhere('product_id', $productId);
                if (!$purchaseItem) {
                    throw new \Exception("Product was not part of original purchase.");
                }

                $prevReturned = $previousReturns[$productId] ?? 0;
                $maxReturnable = $purchaseItem->quantity - $prevReturned;

                if ($returnQty > $maxReturnable) {
                    throw new \Exception("Returned quantity for product {$productId} exceeds allowed maximum of {$maxReturnable}.");
                }

                // Calculate base and GST refund values
                $itemBaseRefund = $returnQty * $purchaseItem->price;
                $itemGstRefund = 0;

                if ($purchase->accepted == 1) {
                    $gstRate = ($purchaseItem->sgst + $purchaseItem->cgst) / 100;
                    $itemGstRefund = $itemBaseRefund * $gstRate;
                }

                $refundAmount += $itemBaseRefund;
                $gstRefundAmount += $itemGstRefund;

                // Create PurchaseReturnItem
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_id' => $productId,
                    'quantity' => $returnQty,
                    'price' => $purchaseItem->price,
                ]);

                // Decrement Product stock (we are sending it back, so stock goes down)
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

            // Update refund totals
            $purchaseReturn->update([
                'refund_amount' => $refundAmount,
                'gst_refund_amount' => $gstRefundAmount,
            ]);

            // Post to double-entry accounting ledger
            $accountingService = new AccountingService($userId);
            $accountingService->postPurchaseReturn($purchaseReturn);

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
}
