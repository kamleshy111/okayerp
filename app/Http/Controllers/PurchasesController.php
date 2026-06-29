<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Services\AccountingService;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchasesController extends Controller
{
    public function index(){

        // $data = Purchase::select('purchases.*','suppliers.name as supplierName', 'suppliers.phone as supplierPhone', 'suppliers.email as supplierEmail')
        //             ->leftJoin('suppliers','purchases.supplier_id', '=', 'suppliers.id')
        //             ->get();

        $userId = Auth::id();
        $purchases = Purchase::whereHas('supplier', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->where('accepted', 1)
        ->with('supplier')
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->id,
                'supplier_name' => $item->supplier->name ?? '--',
                'supplier_phone' => $item->supplier->phone ?? '--',
                'supplier_email' => $item->supplier->email ?? '--',
                'grand_total' => $item->grand_total ?? '--',
                'purchase_Date' => $item->created_at->format('d-m-Y'),
                'payment_status' => $item->payment_status,
            ];
        });


        $products = Product::where('user_id', $userId)->get();
        $suppliers = Supplier::where('user_id', $userId)->get();

        return Inertia::render('Purchase/Index',[
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    public function create(){

        $userId = Auth::id();

        $products = [];
        $categories = \App\Models\Category::where('user_id', $userId)->select('id', 'name')->get();
        $unitTypes = config('units.types') ?? [];
        $gstRates = \App\Models\GstRate::where('is_active', true)->get();

        return Inertia::render('Purchase/Create',[
            'products' => $products,
            'categories' => $categories,
            'unitTypes' => $unitTypes,
            'gstRates' => $gstRates,
        ]);
    }

    public function store(Request $request){


        $validated = $request->validate([
            'supplier_id' => 'required',
            'purchase_items.*.product_id' => 'required',

        ], [
            'supplier_id.required' => 'Supplier name is required.',
            'purchase_items.*.product_id.required' => 'Product is required.',
        ]);

        if (!$validated) {
            return response()->json(["message" => $validated]);
        }

        $lastClosedDate = Auth::user()->last_closed_date;
        if ($lastClosedDate && $request->input('purchase_date') <= $lastClosedDate) {
            return response()->json(['message' => 'Cannot create transactions on or before the last closed date (' . $lastClosedDate . ').'], 403);
        }

        // Begin a database transaction
        DB::beginTransaction();

        try {

            // Fetch supplier to determine if they have a GSTIN
            $supplier = Supplier::find($request->input('supplier_id'));
            $isRefundable = ($supplier && !empty(trim($supplier->gstin))) ? 1 : 0;

            // 1. Insert into `purchases` table
            $purchase = Purchase::create([
                'supplier_id' => $request->input('supplier_id'),
                'invoice_no' => $request->input('invoice_no'),
                'purchase_date' => $request->input('purchase_date'),
                'transport_amount' => $request->input('transport') ?? 0,
                'grand_total' => $request->input('grand_total') ?? 0.00,
                'total_amount' => $request->input('total_amount') ?? 0.00,
                'gst_amount' => $request->input('GstAmount') ?? 0.00,
                'accepted' => $request->input('accepted') ?? 0,
                'paid'  => $request->input('paid') ?? 0.00,
                'payment_method' => $request->input('payment_method') ?? "",
                'payment_status' => $request->input('payment_status') ?? "Unpaid",
                'received_date' => $request->input('received_date'),
                'delivery_mode' => $request->input('delivery_mode'),
                'delivery_person_name' => $request->input('delivery_person_name'),
                'delivery_person_phone' => $request->input('delivery_person_phone'),
                'vehicle_type' => $request->input('vehicle_type'),
                'vehicle_number' => $request->input('vehicle_number'),
                'is_refundable' => $isRefundable,
            ]);

            // 2. Insert purchase items and update stock quantity
            foreach ($request->input('purchase_items', []) as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_type' => $item['unit_type'],
                    'price' => $item['price'],
                    'base_price' => $item['baseAmount'] ?? 0.00,
                    'sgst' => $item['sgst'] ?? 0,
                    'cgst' => $item['cgst'] ?? 0,
                ]);

                // Update Stock Quantity of product
                $product = Product::find($item['product_id']);
                if ($product) {
                    $product->stock_quantity += $item['quantity'];
                    $product->save();

                    // Log stock movement
                    \App\Models\StockMovement::create([
                        'user_id' => Auth::id(),
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'type' => 'Addition',
                        'reference_type' => 'Purchase',
                        'reference_id' => $purchase->id,
                        'reason' => "Purchase Bill #{$purchase->id}",
                    ]);
                }
            }

            $accountingService = new AccountingService(Auth::id());
            $accountingService->postPurchase($purchase);

            // Create a PurchasePayment record if down payment is made
            if ($purchase->paid > 0) {
                PurchasePayment::create([
                    'supplier_id' => $purchase->supplier_id,
                    'purchase_id' => $purchase->id,
                    'amount' => $purchase->paid,
                    'payment_date' => $purchase->purchase_date ?: now()->toDateString(),
                    'payment_method' => $purchase->payment_method ?: 'Cash',
                    'note' => "Payment for Purchase Invoice #{$purchase->id}",
                    'accepted' => $purchase->accepted,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'purchase added successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            if (app()->environment('testing')) {
                throw $e;
            }
            return response()->json(['message' => 'An error occurred while saving the purchase. Please try again.'], 500);
        }
    }

    public function payment($id)
    {

        $query = Purchase::where('supplier_id', $id);
        if (session('private_ledger_unlocked') !== true) {
            $query->where('accepted', 1);
        }
        $purchases = $query->get();

        $totalPurchaseAmount = $purchases->sum('grand_total');
        $totalPurchasePaid = $purchases->sum('paid');

        $paymentQuery = PurchasePayment::where('supplier_id', $id)->whereNull('purchase_id');
        if (session('private_ledger_unlocked') !== true) {
            $paymentQuery->where('accepted', 1);
        }
        $totalDirectPaid = $paymentQuery->sum('amount');

        $totalReceived = $totalPurchasePaid + $totalDirectPaid;

        $balance = $totalReceived - $totalPurchaseAmount;

        return response()->json([
            'supplier_id' => $id,
            'due_amount' => $balance < 0 ? abs($balance) : 0,
            'advance_amount' => $balance > 0 ? $balance : 0,
            'status' => $balance === 0 ? 'clear' : ($balance < 0 ? 'due' : 'advance'),
        ]);
    }


    public function edit($id){
        $userId = Auth::id();
        $query = Purchase::whereHas('supplier', fn($q) => $q->where('user_id', $userId))
                        ->with(['items.product', 'supplier']);
        if (session('private_ledger_unlocked') !== true) {
            $query->where('accepted', 1);
        }
        $purchases = $query->find($id);

        if (!$purchases) {
            abort(403, 'Purchase not found or unauthorized access');
        }

        // Example: map each item to include product name and calculated total
        $productItems  = $purchases->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? null,
                'sgst' => $item->sgst,
                'cgst' => $item->cgst,
                'quantity' => $item->quantity,
                'unit_type' => $item->unit_type,
                'price' => $item->price,
                'total' => $item->quantity * $item->price,
            ];
        });

        $allocatedPayment = 0.0;
        $totalPayments = \App\Models\PurchasePayment::where('supplier_id', $purchases->supplier_id)
            ->where('accepted', $purchases->accepted)
            ->sum('amount');
        $allPurchases = Purchase::where('supplier_id', $purchases->supplier_id)
            ->where('accepted', $purchases->accepted)
            ->orderBy('purchase_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($allPurchases as $p) {
            $returnDueDeduction = \App\Models\PurchaseReturn::where('purchase_id', $p->id)->sum('due_deduction');
            $outstanding = (double)$p->grand_total - (double)$p->paid - (double)$returnDueDeduction;
            if ($outstanding < 0) {
                $totalPayments += abs($outstanding);
                continue;
            }
            if ($outstanding == 0) {
                continue;
            }

            $allocated = 0.0;
            if ($totalPayments > 0) {
                if ($totalPayments >= $outstanding) {
                    $allocated = $outstanding;
                    $totalPayments -= $outstanding;
                } else {
                    $allocated = $totalPayments;
                    $totalPayments = 0.0;
                }
            }

            if ($p->id == $purchases->id) {
                $allocatedPayment = $allocated;
                break;
            }
        }

        $userId = Auth::id();

        $productIds = $purchases->items->pluck('product_id')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->get();
        $supplier = Supplier::find($purchases->supplier_id);
        $suppliers = $supplier ? [$supplier] : [];
        $gstRates = \App\Models\GstRate::where('is_active', true)->get();

        return Inertia::render('Purchase/Edit',[
            'products' => $products,
            'suppliers' => $suppliers,
            'productItems' => $productItems,
            'purchases' => $purchases,
            'allocatedPayment' => $allocatedPayment,
            'gstRates' => $gstRates,
        ]);
    }

    public function update(Request $request, $id){

        $validated = $request->validate([
            'supplier_id' => 'required',

        ], [
            'supplier_id.required' => 'Supplier name is required.',
        ]);

        if (!$validated) {
            return response()->json(["message" => $validated]);
        }

        $userId = Auth::id();
        $query = Purchase::whereHas('supplier', fn($q) => $q->where('user_id', $userId));
        if (session('private_ledger_unlocked') !== true) {
            $query->where('accepted', 1);
        }
        $purchase = $query->where('id', $id)->first();

        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found or unauthorized access.'], 403);
        }

        $lastClosedDate = Auth::user()->last_closed_date;
        if ($lastClosedDate) {
            if ($purchase->purchase_date <= $lastClosedDate) {
                return response()->json(['message' => 'Cannot update transactions from a closed financial year.'], 403);
            }
            if ($request->input('purchase_date') <= $lastClosedDate) {
                return response()->json(['message' => 'Cannot change transaction date to a closed financial year.'], 403);
            }
        }

        try {

            DB::transaction(function () use ($request, $id, $userId) {

                $query = Purchase::whereHas('supplier', fn($q) => $q->where('user_id', $userId));
                if (session('private_ledger_unlocked') !== true) {
                    $query->where('accepted', 1);
                }
                $purchases = $query->where('id', $id)->first();

                $supplier = Supplier::find($request->input('supplier_id'));
                $isRefundable = ($supplier && !empty(trim($supplier->gstin))) ? 1 : 0;

                // Update purchase data
                $purchases->update([
                    'supplier_id' => $request->input('supplier_id'),
                    'invoice_no' => $request->input('invoice_no'),
                    'purchase_date' => $request->input('purchase_date'),
                    'transport_amount' => $request->input('transport'),
                    'gst_amount' => $request->input('GstAmount'),
                    'accepted' => $request->input('accepted') ?? 0,
                    'grand_total' => $request->input('grand_total'),
                    'total_amount' => $request->input('total_amount'),
                    'paid'  => $request->input('paid') ?? 0.00,
                    'payment_method' => $request->input('payment_method') ?? "",
                    'payment_status' => $request->input('payment_status') ?? "Unpaid",
                    'received_date' => $request->input('received_date'),
                    'delivery_mode' => $request->input('delivery_mode'),
                    'delivery_person_name' => $request->input('delivery_person_name'),
                    'delivery_person_phone' => $request->input('delivery_person_phone'),
                    'vehicle_type' => $request->input('vehicle_type'),
                    'vehicle_number' => $request->input('vehicle_number'),
                    'is_refundable' => $isRefundable,
                ]);

                //PurchaseItem old get and product in update quantity
                $oldItems = PurchaseItem::where('purchase_id', $id)->get();

                foreach ($oldItems as $oldItem) {
                    $product = Product::find($oldItem->product_id);
                    if ($product) {
                        $product->stock_quantity -= $oldItem->quantity;
                        $product->save();

                        // Log stock movement
                        \App\Models\StockMovement::create([
                            'user_id' => Auth::id(),
                            'product_id' => $oldItem->product_id,
                            'quantity' => $oldItem->quantity,
                            'type' => 'Deduction',
                            'reference_type' => 'Purchase',
                            'reference_id' => $purchases->id,
                            'reason' => "Purchase Edit #{$purchases->id} (Restored)",
                        ]);
                    }
                }

                //PurchaseItem Delete
                PurchaseItem::where('purchase_id', $id)->delete();

                foreach ($request->input('purchase_items', []) as $item) {
                    $purchaseItem = [
                        'purchase_id' => $purchases->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_type' => $item['unit_type'],
                        'price' => $item['price'],
                        'base_price' => $item['baseAmount'] ?? 0.00,
                        'sgst' => $item['sgst'] ?? 0,
                        'cgst' => $item['cgst'] ?? 0,
                    ];

                    // Update Stock Quantity of product
                    $product = Product::find($item['product_id']);
                    if ($product) {
                        $product->stock_quantity += $item['quantity'];
                        $product->save();

                        // Log stock movement
                        \App\Models\StockMovement::create([
                            'user_id' => Auth::id(),
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'type' => 'Addition',
                            'reference_type' => 'Purchase',
                            'reference_id' => $purchases->id,
                            'reason' => "Purchase Edit #{$purchases->id} (Updated)",
                        ]);
                    }

                    // create purchase Item
                    PurchaseItem::create($purchaseItem);
                }

                $accountingService = new AccountingService($userId);
                $accountingService->postPurchase($purchases);

                // Update or create/delete associated PurchasePayment
                $payment = PurchasePayment::where('purchase_id', $purchases->id)->first();
                if ($purchases->paid > 0) {
                    if ($payment) {
                        $payment->update([
                            'supplier_id' => $purchases->supplier_id,
                            'amount' => $purchases->paid,
                            'payment_method' => $purchases->payment_method ?: 'Cash',
                            'accepted' => $purchases->accepted,
                        ]);
                    } else {
                        PurchasePayment::create([
                            'supplier_id' => $purchases->supplier_id,
                            'purchase_id' => $purchases->id,
                            'amount' => $purchases->paid,
                            'payment_date' => $purchases->purchase_date ?: now()->toDateString(),
                            'payment_method' => $purchases->payment_method ?: 'Cash',
                            'note' => "Payment for Purchase Invoice #{$purchases->id}",
                            'accepted' => $purchases->accepted,
                        ]);
                    }
                } else {
                    if ($payment) {
                        $payment->delete();
                    }
                }

            });

            return response()->json(['message' => 'Purchase updated successfully.']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update purchase.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id){

        $query = Purchase::whereHas('supplier', fn($q) => $q->where('user_id', Auth::id()));
        if (session('private_ledger_unlocked') !== true) {
            $query->where('accepted', 1);
        }
        $purchase = $query->find($id);

        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found or unauthorized access.'], 404);
        }

        $lastClosedDate = Auth::user()->last_closed_date;
        if ($lastClosedDate && $purchase->purchase_date <= $lastClosedDate) {
            return response()->json(['message' => 'Cannot delete transactions from a closed financial year.'], 403);
        }

        DB::beginTransaction();

        try {

            PurchaseItem::where('purchase_id', $id)->delete();

            $accountingService = new AccountingService(Auth::id());

            // Find and delete associated PurchasePayment
            $payment = PurchasePayment::where('purchase_id', $id)->first();
            if ($payment) {
                $accountingService->clearEntries('PurchasePayment', $payment->id);
                $payment->delete();
            }

            $purchase->delete();

            $accountingService->clearEntries('Purchase', $id);

            DB::commit();

            return response()->json(['message' => 'Purchase deleted successfully.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Failed to delete purchase.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $userId = Auth::id();
        $query = Purchase::whereHas('supplier', fn($q) => $q->where('user_id', $userId))
                        ->with(['items.product', 'supplier.user']);
        if (session('private_ledger_unlocked') !== true) {
            $query->where('accepted', 1);
        }
        $purchase = $query->find($id);

        if (!$purchase) {
            abort(403, 'Purchase not found or unauthorized access');
        }

        $allocatedPayment = 0.0;
        if ($purchase->supplier) {
            $totalPayments = \App\Models\PurchasePayment::where('supplier_id', $purchase->supplier_id)
                ->where('accepted', $purchase->accepted)
                ->sum('amount');
            $allPurchases = Purchase::where('supplier_id', $purchase->supplier_id)
                ->where('accepted', $purchase->accepted)
                ->orderBy('purchase_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($allPurchases as $p) {
                $returnDueDeduction = \App\Models\PurchaseReturn::where('purchase_id', $p->id)->sum('due_deduction');
                $outstanding = (double)$p->grand_total - (double)$p->paid - (double)$returnDueDeduction;
                if ($outstanding < 0) {
                    $totalPayments += abs($outstanding);
                    continue;
                }
                if ($outstanding == 0) {
                    continue;
                }

                $allocated = 0.0;
                if ($totalPayments > 0) {
                    if ($totalPayments >= $outstanding) {
                        $allocated = $outstanding;
                        $totalPayments -= $outstanding;
                    } else {
                        $allocated = $totalPayments;
                        $totalPayments = 0.0;
                    }
                }

                if ($p->id == $purchase->id) {
                    $allocatedPayment = $allocated;
                    break;
                }
            }
        }

        $returnDueDeduction = \App\Models\PurchaseReturn::where('purchase_id', $purchase->id)->sum('due_deduction');

        return Inertia::render('Purchase/Show', [
            'purchase' => $purchase,
            'allocatedPayment' => $allocatedPayment,
            'returnDueDeduction' => $returnDueDeduction,
        ]);
    }
}
