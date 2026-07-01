<?php

namespace App\Http\Controllers;

use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Customer;
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

        // Get customers who have sales records
        $customers = Customer::where('user_id', $userId)
            ->whereHas('sales', function ($q) {
                if (session('private_ledger_unlocked') !== true) {
                    $q->where('accepted', 1);
                }
            })
            ->orderBy('name', 'asc')
            ->select('id', 'name', 'phone')
            ->get();

        return Inertia::render('SaleReturn/Create', [
            'customers' => $customers,
        ]);
    }

    public function getCustomerSales($customerId)
    {
        $userId = Auth::id();

        // Fetch sales belonging to this customer and user
        $salesQuery = Sale::where('customer_id', $customerId)
            ->whereHas('customer', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        if (session('private_ledger_unlocked') !== true) {
            $salesQuery->where('accepted', 1);
        }

        $sales = $salesQuery->orderBy('id', 'desc')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'invoice_label' => "Invoice #{$item->id} - Date: " . $item->created_at->format('d-M-Y') . " (Total: ₹" . number_format($item->grand_total, 2) . ")",
            ];
        });

        return response()->json($sales);
    }

    public function getCustomerPurchasedItems($customerId)
    {
        $userId = Auth::id();

        // Get sales belonging to this customer and user
        $salesQuery = Sale::where('customer_id', $customerId)
            ->whereHas('customer', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        if (session('private_ledger_unlocked') !== true) {
            $salesQuery->where('accepted', 1);
        }

        $sales = $salesQuery->with(['saleItems.product'])->get();

        // For each sale item, calculate previously returned quantities
        $saleIds = $sales->pluck('id')->toArray();

        $previousReturns = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
            ->whereIn('sale_returns.sale_id', $saleIds)
            ->groupBy('sale_returns.sale_id', 'sale_return_items.product_id')
            ->select('sale_returns.sale_id', 'sale_return_items.product_id', DB::raw('SUM(sale_return_items.quantity) as total_returned'))
            ->get()
            ->keyBy(function ($item) {
                return $item->sale_id . '-' . $item->product_id;
            })
            ->toArray();

        // Calculate customer's Net Balance (due) just like in CustomersController
        $dueAmountSum = 0;
        $advanceAmountSum = 0;

        $customerSales = Sale::where('customer_id', $customerId);
        if (session('private_ledger_unlocked') !== true) {
            $customerSales->where('accepted', 1);
        }
        $customerSales = $customerSales->get();

        foreach ($customerSales as $s) {
            $paymentsSum = \App\Models\SalePayment::where('sale_id', $s->id);
            if (session('private_ledger_unlocked') !== true) {
                $paymentsSum->where('accepted', 1);
            }
            $actualPaid = $paymentsSum->sum('amount');

            $dueDeductionsSum = (float)\App\Models\SaleReturnItem::where('sale_id', $s->id)->sum('due_deduction');

            $storeCreditRefundsSum = (float)\App\Models\SaleReturn::where('sale_id', $s->id)
                ->where('refund_method', 'Store Credit')
                ->get()
                ->sum(fn($r) => (float)$r->refund_amount + (float)$r->gst_refund_amount);

            $saleBalance = $actualPaid - (float)$s->grand_total + $dueDeductionsSum;
            if ($saleBalance < 0) {
                $dueAmountSum += abs($saleBalance);
            } elseif ($saleBalance > 0) {
                $advanceAmountSum += $saleBalance;
            }

            $advanceAmountSum += $storeCreditRefundsSum;
        }

        $totalDirectPaid = \App\Models\SalePayment::where('customer_id', $customerId)->whereNull('sale_id');
        if (session('private_ledger_unlocked') !== true) {
            $totalDirectPaid->where('accepted', 1);
        }
        $advanceAmountSum += $totalDirectPaid->sum('amount');

        $customerTotalDue = max(0, $dueAmountSum - $advanceAmountSum);

        $items = [];
        foreach ($sales as $sale) {
            $previousDueDeductions = \App\Models\SaleReturnItem::where('sale_id', $sale->id)->sum('due_deduction');
            $dueAmount = max(0, (float)$sale->grand_total - (float)$sale->paid - $previousDueDeductions);

            foreach ($sale->saleItems as $saleItem) {
                if (!$saleItem->product) {
                    continue;
                }

                $key = $sale->id . '-' . $saleItem->product_id;
                $prevReturned = isset($previousReturns[$key]) ? (int)$previousReturns[$key]->total_returned : 0;
                $availableQty = max(0, $saleItem->quantity - $prevReturned);

                if ($availableQty > 0) {
                    $items[] = [
                        'sale_item_id' => $saleItem->id,
                        'sale_id' => $sale->id,
                        'product_id' => $saleItem->product_id,
                        'product_name' => $saleItem->product->name,
                        'price' => (float)$saleItem->price,
                        'cgst' => (float)$saleItem->cgst,
                        'sgst' => (float)$saleItem->sgst,
                        'sold_qty' => $saleItem->quantity,
                        'returned_qty' => $prevReturned,
                        'available_qty' => $availableQty,
                        'invoice_label' => "Invoice #{$sale->id} - Date: " . $sale->created_at->format('d-M-Y'),
                        'accepted' => $sale->accepted,
                        'sale_due_amount' => $dueAmount,
                    ];
                }
            }
        }

        return response()->json([
            'items' => $items,
            'customer_total_due' => round($customerTotalDue, 2),
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

        $previousDueDeductions = \App\Models\SaleReturnItem::where('sale_id', $sale->id)->sum('due_deduction');

        return response()->json([
            'sale_id' => $sale->id,
            'customer_name' => $sale->customer->name ?? '',
            'accepted' => $sale->accepted,
            'due_amount' => max(0, (float)$sale->grand_total - (float)$sale->paid - $previousDueDeductions),
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_id' => 'required_without:customer_id|exists:sales,id',
            'customer_id' => 'required_without:sale_id|exists:customers,id',
            'return_date' => 'required|date',
            'refund_method' => 'required|string',
            'reason' => 'nullable|string',
            'due_deduction' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);


        $userId = Auth::id();
        $saleId = $request->input('sale_id');
        $customerId = $request->input('customer_id');

        if (!$customerId && $saleId) {
            $sale = Sale::find($saleId);
            $customerId = $sale ? $sale->customer_id : null;
        }

        $customer = Customer::where('user_id', $userId)->find($customerId);
        if (!$customer) {
            return response()->json(['message' => 'Unauthorized or invalid customer.'], 403);
        }

        // Group items by sale_id, resolving item sale_id from parent fallback if missing
        $itemsBySale = [];
        $resolvedItems = [];
        foreach ($request->input('items') as $item) {
            $itemSaleId = $item['sale_id'] ?? $saleId;
            if (!$itemSaleId) {
                return response()->json(['message' => 'Missing sale_id for returned products.'], 422);
            }
            $item['sale_id'] = $itemSaleId;
            $resolvedItems[] = $item;
            $itemsBySale[$itemSaleId][] = $item;
        }

        DB::beginTransaction();

        try {
            // Generate Return Number RET-XXXXX
            $lastReturn = SaleReturn::where('user_id', $userId)->orderBy('id', 'desc')->first();

            if ($lastReturn) {
                $parts = explode('-', $lastReturn->return_no);
                $nextNumber = ((int) end($parts)) + 1;
            } else {
                $nextNumber = 1;
            }
            $returnNo = 'RET-' . $userId . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            // Fetch all involved sales
            $salesList = Sale::whereIn('id', array_keys($itemsBySale))
                ->with('saleItems')
                ->get()
                ->keyBy('id');

            $firstItem = collect($resolvedItems)->first();
            $firstSaleId = $firstItem ? $firstItem['sale_id'] : null;

            // Create single return record
            $saleReturn = SaleReturn::create([
                'user_id' => $userId,
                'sale_id' => $firstSaleId, // For database schema backwards compatibility
                'customer_id' => $customer->id,
                'return_no' => $returnNo,
                'return_date' => $request->input('return_date'),
                'refund_method' => $request->input('refund_method'),
                'reason' => $request->input('reason'),
                'refund_amount' => 0,
                'gst_refund_amount' => 0,
                'due_deduction' => 0,
            ]);

            // Calculate due deductions per sale
            $requestedDueDeduction = (float)$request->input('due_deduction', 0);
            $remainingDeduction = $requestedDueDeduction;
            $deductionsBySale = [];

            // Compute total refunds per sale and allocate due deduction
            foreach ($itemsBySale as $saleId => $saleItems) {
                $sale = $salesList[$saleId] ?? null;
                if (!$sale) {
                    throw new \Exception("Invalid sale invoice #{$saleId}");
                }

                $previousDueDeductions = \App\Models\SaleReturnItem::where('sale_id', $saleId)->sum('due_deduction');
                $dueOnSale = max(0, (float)$sale->grand_total - (float)$sale->paid - $previousDueDeductions);

                $saleRefundTotal = 0.0;
                foreach ($saleItems as $item) {
                    $saleItem = $sale->saleItems->firstWhere('product_id', $item['product_id']);
                    if (!$saleItem) {
                        throw new \Exception("Product {$item['product_id']} was not part of original sale #{$saleId}.");
                    }
                    $itemBase = $item['quantity'] * $saleItem->price;
                    $itemGst = 0.0;
                    if ($sale->accepted == 1) {
                        $gstRate = ($saleItem->sgst + $saleItem->cgst) / 100;
                        $itemGst = $itemBase * $gstRate;
                    }
                    $saleRefundTotal += $itemBase + $itemGst;
                }

                $allocated = min($remainingDeduction, $dueOnSale);

                $deductionsBySale[$saleId] = $allocated;
                $remainingDeduction -= $allocated;
            }

            $refundAmount = 0;
            $gstRefundAmount = 0;
            $actualDueDeduction = 0;

            // Process returned products
            foreach ($itemsBySale as $saleId => $saleItems) {
                $sale = $salesList[$saleId];

                // Get previously returned quantities specifically for this sale
                $previousReturns = SaleReturnItem::where('sale_id', $saleId)
                    ->groupBy('product_id')
                    ->select('product_id', DB::raw('SUM(quantity) as total_returned'))
                    ->pluck('total_returned', 'product_id')
                    ->toArray();

                foreach ($saleItems as $item) {
                    $productId = $item['product_id'];
                    $returnQty = (int) $item['quantity'];

                    $saleItem = $sale->saleItems->firstWhere('product_id', $productId);
                    $prevReturned = $previousReturns[$productId] ?? 0;
                    $maxReturnable = $saleItem->quantity - $prevReturned;

                    if ($returnQty > $maxReturnable) {
                        throw new \Exception("Returned quantity for product {$productId} exceeds allowed maximum of {$maxReturnable}.");
                    }

                    $itemBaseRefund = $returnQty * $saleItem->price;
                    $itemGstRefund = 0;

                    if ($sale->accepted == 1) {
                        $gstRate = ($saleItem->sgst + $saleItem->cgst) / 100;
                        $itemGstRefund = $itemBaseRefund * $gstRate;
                    }

                    $refundAmount += $itemBaseRefund;
                    $gstRefundAmount += $itemGstRefund;

                    // Allocate due deduction to the item
                    $itemDueDeduction = 0.0;
                    if (isset($deductionsBySale[$saleId]) && $deductionsBySale[$saleId] > 0) {
                        $itemDueDeduction = $deductionsBySale[$saleId];
                        $actualDueDeduction += $itemDueDeduction;
                        $deductionsBySale[$saleId] = 0; // Clear after allocation
                    }

                    // Create SaleReturnItem
                    SaleReturnItem::create([
                        'sale_return_id' => $saleReturn->id,
                        'sale_id' => $saleId,
                        'product_id' => $productId,
                        'quantity' => $returnQty,
                        'price' => $saleItem->price,
                        'due_deduction' => $itemDueDeduction,
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
            }

            // Update final return values
            $saleReturn->update([
                'refund_amount' => $refundAmount,
                'gst_refund_amount' => $gstRefundAmount,
                'due_deduction' => $actualDueDeduction,
            ]);

            // Post to double-entry accounting ledger
            $accountingService = new AccountingService($userId);
            $accountingService->postSaleReturn($saleReturn);

            // Update payment status for each sale
            foreach (array_keys($itemsBySale) as $saleId) {
                $sale = $salesList[$saleId];
                $totalDueDeductions = \App\Models\SaleReturnItem::where('sale_id', $saleId)->sum('due_deduction');
                $effectiveBalance = max(0, (float)$sale->grand_total - (float)$sale->paid - $totalDueDeductions);

                if ($effectiveBalance <= 0) {
                    $sale->payment_status = 'Paid';
                } elseif ((float)$sale->paid + $totalDueDeductions <= 0) {
                    $sale->payment_status = 'Unpaid';
                } else {
                    $sale->payment_status = 'Partial';
                }
                $sale->save();
            }

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

        $return = SaleReturn::where('user_id', $userId)
        ->with(['customer.user', 'items.product'])
        ->find($id);

        if (!$return) {
            abort(403, 'Return not found or unauthorized access.');
        }

        $pdf = Pdf::loadView('return_invoice', compact('return'))->setPaper('a4');
        return $pdf->stream("return_invoice_{$return->return_no}.pdf");
    }
}
