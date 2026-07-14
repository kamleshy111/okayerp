<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\ReferralUser;
use App\Models\ReferralSale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use App\Services\AccountingService;

class SaleController extends Controller
{
    public function index(){

        // $data = Sale::select('sales.*','customers.name as customerName', 'customers.email', 'customers.phone',)
        //             ->leftJoin('customers','sales.customer_id', '=', 'customers.id')
        //             ->get();

        $userId = Auth::id();
        $query = Sale::query();
        if (Auth::user()->role !== 'admin') {
            $query->whereHas('customer', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
            if (session('private_ledger_unlocked') !== true) {
                $query->where(function($q) {
                    $q->where('accepted', 1)
                      ->orWhere(fn($q2) => $q2->whereNotNull('currency')->where('currency', '!=', 'INR'));
                });
            }
        }
        $sales = $query->with(['customer', 'saleReturns', 'saleReturnItems'])
        ->get()
        ->map(function ($item) {
            $dueDeductions = $item->saleReturnItems ? $item->saleReturnItems->sum('due_deduction') : 0;
            $effectiveGrandTotal = max(0, $item->grand_total);

            $hasReturn = $item->saleReturns->isNotEmpty();
            $isWithinTenMinutes = $item->created_at->diffInMinutes(now(), false) <= 10;

            return [
                'id' => $item->id,
                'customerName' => $item->customer->name ?? '',
                'email' => $item->customer->email ?? '',
                'phone' => $item->customer->phone ?? '',
                'grand_total' => number_format($effectiveGrandTotal, 2, '.', ''),
                'sale_date' => $item->sale_date ? \Carbon\Carbon::parse($item->sale_date)->format('d-m-Y') : $item->created_at->format('d-m-Y'),
                'payment_status' => $item->paid >= $effectiveGrandTotal ? 'Paid' : $item->payment_status,
                'is_deletable' => !$hasReturn && $isWithinTenMinutes,
            ];
        });

        return Inertia::render('Sale/Index',[
            'sales' => $sales,
        ]);
    }

    public function create(){

        $userId = Auth::id();

        $products = [];
        $categories = \App\Models\Category::where('user_id', $userId)->select('id', 'name')->get();
        $unitTypes = config('units.types') ?? [];
        $gstRates = \App\Models\GstRate::where('is_active', true)->get();
        $referralUsers = ReferralUser::where('user_id', $userId)
            ->select('id', 'name', 'phone')
            ->orderByRaw('(SELECT MAX(created_at) FROM referral_sales WHERE referral_sales.referral_user_id = referral_users.id) DESC')
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->get();
        return Inertia::render('Sale/Create',[
            'products' => $products,
            'categories' => $categories,
            'unitTypes' => $unitTypes,
            'gstRates' => $gstRates,
            'referralUsers' => $referralUsers,
        ]);
    }

    public function store(Request $request){

        $validated = $request->validate([
            'customer_id' => 'required',
            'sale_date' => 'nullable|date',
            'sale_items.*.product_id' => 'required',
        ], [
            'customer_id.required' => 'Customer name is required.',
            'sale_items.*.product_id.required' => 'Product is required.',
        ]);

        if (!$validated) {
            return response()->json(["message" => $validated]);
        }

        $userId = Auth::id();

        $lastClosedDate = Auth::user()->last_closed_date;
        if ($lastClosedDate && now()->toDateString() <= $lastClosedDate) {
            return response()->json(['message' => 'Cannot create transactions on or before the last closed date (' . $lastClosedDate . ').'], 403);
        }

        // Validate customer belongs to logged-in user
        $customerExists = Customer::where('user_id', $userId)->where('id', $request->input('customer_id'))->exists();
        if (!$customerExists) {
            return response()->json(['message' => 'Selected customer is invalid or unauthorized.'], 403);
        }

        // Validate products belong to logged-in user
        $productIds = collect($request->input('sale_items', []))->pluck('product_id')->unique();
        if ($productIds->isNotEmpty()) {
            $validProductsCount = Product::where('user_id', $userId)->whereIn('id', $productIds)->count();
            if ($validProductsCount !== $productIds->count()) {
                return response()->json(['message' => 'One or more selected products are invalid or unauthorized.'], 403);
            }
        }

        // Begin a database transaction
        DB::beginTransaction();

        try {

            // 1. Insert into `sales` table
            $sale = Sale::create([
                'customer_id' => $request->input('customer_id'),
                'estimate_id' => $request->input('estimate_id'),
                'referral_user_id' => $request->input('referral_user_id') ?: null,
                'sale_date' => $request->input('sale_date') ?: now()->toDateString(),
                'grand_total' => $request->input('grand_total') ?? 0.00,
                'total_amount' => $request->input('total_amount') ?? 0.00,
                'gst_amount' => $request->input('GstAmount') ?? 0.00,
                'accepted' => $request->input('accepted') ?? 0,
                'paid'  => $request->input('paid') ?? 0.00,
                'payment_method' => $request->input('payment_method') ?? "",
                'payment_status' => $request->input('payment_status') ?? "Unpaid",
                'discount'  => $request->input('discount') ?? 0,
                'currency' => $request->input('currency') ?: 'INR',
                'exchange_rate' => $request->input('exchange_rate') ?: 1.0000,
            ]);

            // Update Estimate status to Invoiced if estimate_id is passed
            if ($request->filled('estimate_id')) {
                $estimate = \App\Models\Estimate::whereHas('customer', fn($q) => $q->where('user_id', $userId))
                    ->find($request->input('estimate_id'));
                if ($estimate) {
                    $estimate->update(['status' => 'Invoiced']);
                }
            }

            // 2. Insert sale items and update stock quantity
            foreach ($request->input('sale_items', []) as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_type' => $item['unit_type'],
                    'price' => $item['price'],
                    'base_price' => $item['baseAmount'] ?? 0.00,
                    'sgst' => $item['sgst'] ?? 0,
                    'cgst' => $item['cgst'] ?? 0,
                    'width' => $item['width'] ?? null,
                    'height' => $item['height'] ?? null,
                    'alternate_quantity' => $item['alternate_quantity'] ?? null,
                    'alternate_unit_type' => $item['alternate_unit_type'] ?? null,
                ]);

                // Update Stock Quantity of product
                $product = Product::where('user_id', $userId)->find($item['product_id']);
                if ($product) {
                    $product->stock_quantity -= $item['quantity'];
                    $product->save();

                    // Log stock movement
                    \App\Models\StockMovement::create([
                        'user_id' => $userId,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'type' => 'Deduction',
                        'reference_type' => 'Sale',
                        'reference_id' => $sale->id,
                        'reason' => "Sale Invoice #{$sale->id}",
                    ]);
                }
            }

            $accountingService = new AccountingService($userId);
            $accountingService->postSale($sale);

            // Save ReferralSale if referral_user_id provided
            if ($sale->referral_user_id) {
                ReferralSale::create([
                    'sale_id'          => $sale->id,
                    'referral_user_id' => $sale->referral_user_id,
                    'sale_amount'      => $sale->grand_total,
                ]);
            }

            // Create a SalePayment record if down payment is made
            if ($sale->paid > 0) {
                SalePayment::create([
                    'customer_id' => $sale->customer_id,
                    'sale_id' => $sale->id,
                    'amount' => $sale->paid,
                    'payment_date' => $sale->created_at ? $sale->created_at->toDateString() : now()->toDateString(),
                    'payment_method' => $sale->payment_method ?: 'Cash',
                    'note' => "Payment for Sale Invoice #{$sale->id}",
                    'accepted' => $sale->accepted,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'sale added successfully.',
                'invoice_url' => route('sale.invoice.download', ['id' => $sale->id]),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Sale Error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            if (app()->environment('testing')) {
                throw $e;
            }
            return response()->json(['message' => 'An error occurred while saving the sale. Please try again.', 'error' => $e->getMessage()], 500);
        }
    }

    public function payment($id)
    {
        $userId = Auth::id();
        $customer = Customer::where('user_id', $userId)->find($id);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found or unauthorized access.'], 403);
        }

        $query = Sale::whereHas('customer', fn($q) => $q->where('user_id', $userId))
                    ->where('customer_id', $id)
                    ->with('saleReturns');
        if (session('private_ledger_unlocked') !== true) {
            $query->where(function($q) {
                $q->where('accepted', 1)
                  ->orWhere(fn($q2) => $q2->whereNotNull('currency')->where('currency', '!=', 'INR'));
            });
        }
        $sale = $query->get();

        $dueAmount = 0;
        $advanceAmount = 0;

        foreach ($sale as $s) {
            $paymentsSum = SalePayment::where('sale_id', $s->id);
            if (session('private_ledger_unlocked') !== true) {
                $paymentsSum->where('accepted', 1);
            }
            $actualPaid = $paymentsSum->sum('amount');
            $saleBalance = $actualPaid - $s->grand_total;
            if ($saleBalance < 0) {
                $dueAmount += abs($saleBalance);
            } elseif ($saleBalance > 0) {
                $advanceAmount += $saleBalance;
            }
        }

        $paymentQuery = SalePayment::whereHas('customer', fn($q) => $q->where('user_id', $userId))
                                      ->where('customer_id', $id)
                                      ->whereNull('sale_id');
        if (session('private_ledger_unlocked') !== true) {
            $paymentQuery->where('accepted', 1);
        }
        $totalDirectPaid = $paymentQuery->sum('amount');

        $advanceAmount += $totalDirectPaid;

        $netBalance = $advanceAmount - $dueAmount;

        return response()->json([
            'customer_id' => $id,
            'due_amount' => $dueAmount,
            'advance_amount' => $advanceAmount,
            'status' => $netBalance === 0 ? 'clear' : ($netBalance < 0 ? 'due' : 'advance'),
        ]);
    }

    public function edit($id){

        $query = Sale::query()->with(['saleItems.product', 'customer']);
        if (Auth::user()->role !== 'admin') {
            $query->whereHas('customer', fn($q) => $q->where('user_id', Auth::id()));
            if (session('private_ledger_unlocked') !== true) {
                $query->where(function($q) {
                    $q->where('accepted', 1)
                      ->orWhere(fn($q2) => $q2->whereNotNull('currency')->where('currency', '!=', 'INR'));
                });
            }
        }
        $sales = $query->find($id);

        if (!$sales) {
            abort(403, 'Sale not found or unauthorized access');
        }

        $productItems = $sales->saleItems->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? null,
                'sgst' => $item->sgst,
                'cgst' => $item->cgst,
                'quantity' => $item->quantity,
                'unit_type' => $item->unit_type,
                'width' => $item->width,
                'height' => $item->height,
                'alternate_quantity' => $item->alternate_quantity,
                'alternate_unit_type' => $item->alternate_unit_type,
                'price' => $item->price,
                'base_price' => $item->base_price,
                'total' => $item->quantity * $item->price,
            ];
        });

        $allocatedPayment = 0.0;
        $totalPayments = \App\Models\SalePayment::where('customer_id', $sales->customer_id)
            ->where('accepted', $sales->accepted)
            ->whereNull('sale_id')
            ->sum('amount');
        $allSales = Sale::where('customer_id', $sales->customer_id)
            ->where('accepted', $sales->accepted)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($allSales as $s) {
            $returnDueDeduction = \App\Models\SaleReturnItem::where('sale_id', $s->id)->sum('due_deduction');
            $outstanding = (double)$s->grand_total - (double)$s->paid - (double)$returnDueDeduction;
            if ($outstanding <= 0) {
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

            if ($s->id == $sales->id) {
                $allocatedPayment = $allocated;
                break;
            }
        }

        $userId = Auth::id();

        $productIds = $sales->saleItems->pluck('product_id')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->get();
        $customer = Customer::find($sales->customer_id);
        $customers = $customer ? [$customer] : [];

        $gstRates = \App\Models\GstRate::where('is_active', true)->get();
        $referralUsers = ReferralUser::where('user_id', $userId)
            ->select('id', 'name', 'phone')
            ->orderByRaw('(SELECT MAX(created_at) FROM referral_sales WHERE referral_sales.referral_user_id = referral_users.id) DESC')
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->get();
        if ($sales->referral_user_id) {
            $currentReferral = ReferralUser::find($sales->referral_user_id);
            if ($currentReferral && !$referralUsers->contains('id', $sales->referral_user_id)) {
                $referralUsers->push($currentReferral);
            }
        }

        return Inertia::render('Sale/Edit',[
            'products' => $products,
            'customers' => $customers,
            'productItems' => $productItems,
            'sales' => $sales,
            'allocatedPayment' => $allocatedPayment,
            'gstRates' => $gstRates,
            'referralUsers' => $referralUsers,
        ]);
    }

    public function update(Request $request, $id){

        $validated = $request->validate([
            'customer_id' => 'required',

        ], [
            'customer_id.required' => 'Customer name is required.',
        ]);

        if (!$validated) {
            return response()->json(["message" => $validated]);
        }

        $userId = Auth::id();

        // Validate sale exists and belongs to the user
        $query = Sale::query();
        if (Auth::user()->role !== 'admin') {
            $query->whereHas('customer', fn($q) => $q->where('user_id', $userId));
            if (session('private_ledger_unlocked') !== true) {
                $query->where(function($q) {
                    $q->where('accepted', 1)
                      ->orWhere(fn($q2) => $q2->whereNotNull('currency')->where('currency', '!=', 'INR'));
                });
            }
        }
        $sale = $query->where('id', $id)->first();

        if (!$sale) {
            return response()->json(['message' => 'Sale not found or unauthorized access.'], 403);
        }

        $lastClosedDate = Auth::user()->last_closed_date;
        if ($lastClosedDate && $sale->created_at->toDateString() <= $lastClosedDate) {
            return response()->json(['message' => 'Cannot update transactions from a closed financial year.'], 403);
        }

        // Validate customer belongs to logged-in user
        $customerExists = Customer::where('user_id', $userId)->where('id', $request->input('customer_id'))->exists();
        if (!$customerExists) {
            return response()->json(['message' => 'Selected customer is invalid or unauthorized.'], 403);
        }

        // Validate products belong to logged-in user
        $productIds = collect($request->input('sale_items', []))->pluck('product_id')->unique();
        if ($productIds->isNotEmpty()) {
            $validProductsCount = Product::where('user_id', $userId)->whereIn('id', $productIds)->count();
            if ($validProductsCount !== $productIds->count()) {
                return response()->json(['message' => 'One or more selected products are invalid or unauthorized.'], 403);
            }
        }

        try {

            DB::transaction(function () use ($request, $id, $userId) {

                $query = Sale::whereHas('customer', fn($q) => $q->where('user_id', $userId));
                if (session('private_ledger_unlocked') !== true) {
                    $query->where('accepted', 1);
                }
                $sale = $query->where('id', $id)->first();

                // Update purchase data
                $sale->update([
                    'customer_id' => $request->input('customer_id'),
                    'referral_user_id' => $request->input('referral_user_id') ?: null,
                    'sale_date' => $request->input('sale_date') ?: now()->toDateString(),
                    'gst_amount' => $request->input('GstAmount'),
                    'accepted' => $request->input('accepted') ?? 0,
                    'grand_total' => $request->input('grand_total'),
                    'total_amount' => $request->input('total_amount'),
                    'paid'  => $request->input('paid') ?? 0.00,
                    'payment_method' => $request->input('payment_method') ?? "",
                    'payment_status' => $request->input('payment_status') ?? "Unpaid",
                    'discount'  => $request->input('discount') ?? 0,
                    'currency' => $request->input('currency') ?: 'INR',
                    'exchange_rate' => $request->input('exchange_rate') ?: 1.0000,
                ]);

                //SaleItem old get and product in update quantity
                $oldItems = SaleItem::where('sale_id', $id)->get();

                foreach ($oldItems as $oldItem) {
                    $product = Product::where('user_id', $userId)->find($oldItem->product_id);
                    if ($product) {
                        $product->stock_quantity += $oldItem->quantity;
                        $product->save();

                        // Log stock movement
                        \App\Models\StockMovement::create([
                            'user_id' => $userId,
                            'product_id' => $oldItem->product_id,
                            'quantity' => $oldItem->quantity,
                            'type' => 'Addition',
                            'reference_type' => 'Sale',
                            'reference_id' => $sale->id,
                            'reason' => "Sale Edit #{$sale->id} (Restored)",
                        ]);
                    }
                }

                SaleItem::where('sale_id', $id)->delete();

                foreach ($request->input('sale_items', []) as $item) {
                    $saleItem = [
                        'sale_id' => $sale->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_type' => $item['unit_type'],
                        'price' => $item['price'],
                        'base_price' => $item['baseAmount'] ?? 0.00,
                        'sgst' => $item['sgst'] ?? 0,
                        'cgst' => $item['cgst'] ?? 0,
                        'width' => $item['width'] ?? null,
                        'height' => $item['height'] ?? null,
                        'alternate_quantity' => $item['alternate_quantity'] ?? null,
                        'alternate_unit_type' => $item['alternate_unit_type'] ?? null,
                    ];

                    // Update Stock Quantity of product
                    $product = Product::where('user_id', $userId)->find($item['product_id']);
                    if ($product) {
                        $product->stock_quantity -= $item['quantity'];
                        $product->save();

                        // Log stock movement
                        \App\Models\StockMovement::create([
                            'user_id' => $userId,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'type' => 'Deduction',
                            'reference_type' => 'Sale',
                            'reference_id' => $sale->id,
                            'reason' => "Sale Edit #{$sale->id} (Updated)",
                        ]);
                    }

                    SaleItem::create($saleItem);
                }

                $accountingService = new AccountingService($userId);
                $accountingService->postSale($sale);

                // Update or create/delete ReferralSale
                if ($sale->referral_user_id) {
                    ReferralSale::updateOrCreate(
                        ['sale_id' => $sale->id],
                        [
                            'referral_user_id' => $sale->referral_user_id,
                            'sale_amount'      => $sale->grand_total,
                        ]
                    );
                } else {
                    ReferralSale::where('sale_id', $sale->id)->delete();
                }

                // Update or create/delete associated SalePayment
                $payment = SalePayment::where('sale_id', $sale->id)->orderBy('id', 'asc')->first();
                if ($sale->paid > 0) {
                    if ($payment) {
                        $payment->update([
                            'customer_id' => $sale->customer_id,
                            'amount' => $sale->paid,
                            'payment_method' => $sale->payment_method ?: 'Cash',
                            'accepted' => $sale->accepted,
                        ]);
                    } else {
                        SalePayment::create([
                            'customer_id' => $sale->customer_id,
                            'sale_id' => $sale->id,
                            'amount' => $sale->paid,
                            'payment_date' => $sale->created_at ? $sale->created_at->toDateString() : now()->toDateString(),
                            'payment_method' => $sale->payment_method ?: 'Cash',
                            'note' => "Payment for Sale Invoice #{$sale->id}",
                            'accepted' => $sale->accepted,
                        ]);
                    }
                } else {
                    if ($payment) {
                        $payment->delete();
                    }
                }

            });

            return response()->json(['message' => 'Sale updated successfully.']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update sale.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadInvoice(Request $request,$id){

        $query = Sale::query()->with(['saleItems.product', 'customer.user']);
        if (Auth::user()->role !== 'admin') {
            $query->whereHas('customer', fn($q) => $q->where('user_id', Auth::id()));
            if (session('private_ledger_unlocked') !== true) {
                $query->where(function($q) {
                    $q->where('accepted', 1)
                      ->orWhere(fn($q2) => $q2->whereNotNull('currency')->where('currency', '!=', 'INR'));
                });
            }
        }
        $sale = $query->find($id);

        if (!$sale) {
            abort(403, 'Sale not found or unauthorized access');
        }



        $allocatedPayment = 0.0;
        if ($sale->customer) {
            $totalPayments = \App\Models\SalePayment::where('customer_id', $sale->customer_id)
                ->where('accepted', $sale->accepted)
                ->whereNull('sale_id')
                ->sum('amount');
            $allSales = Sale::where('customer_id', $sale->customer_id)
                ->where('accepted', $sale->accepted)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($allSales as $s) {
                $returnDueDeduction = \App\Models\SaleReturnItem::where('sale_id', $s->id)->sum('due_deduction');
                $outstanding = (double)$s->grand_total - (double)$s->paid - (double)$returnDueDeduction;
                if ($outstanding <= 0) {
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

                if ($s->id == $sale->id) {
                    $allocatedPayment = $allocated;
                    break;
                }
            }
        }
        $allocatedPayment = round($allocatedPayment, 2);
        $returnDueDeduction = \App\Models\SaleReturnItem::where('sale_id', $sale->id)->sum('due_deduction');

        $customer = $sale->customer;
        $isExport = $customer && !empty($customer->country) && strtolower(trim($customer->country)) !== 'india';
        $isA5 = !$isExport && ($sale->gst_amount ?? 0) <= 0;

        $viewName = $isA5 ? 'a5_invoice' : 'invoice';
        $paperSize = $isA5 ? 'a5' : 'a4';
        $paperOrientation = $isA5 ? 'landscape' : 'portrait';

        $pdf = Pdf::loadView($viewName, compact('sale', 'allocatedPayment', 'returnDueDeduction'))
            ->setPaper($paperSize, $paperOrientation);

        return $pdf->stream("invoice_{$sale->id}.pdf");
    }

    public function destroy($id){

        $query = Sale::query();
        if (Auth::user()->role !== 'admin') {
            $query->whereHas('customer', fn($q) => $q->where('user_id', Auth::id()));
            if (session('private_ledger_unlocked') !== true) {
                $query->where(function($q) {
                    $q->where('accepted', 1)
                      ->orWhere(fn($q2) => $q2->whereNotNull('currency')->where('currency', '!=', 'INR'));
                });
            }
        }
        $sale = $query->find($id);

        if (!$sale) {
            return response()->json(['message' => 'Sale not found.'], 404);
        }

        $hasReturn = \App\Models\SaleReturn::where('sale_id', $id)->exists();
        if ($hasReturn) {
            return response()->json(['message' => 'Cannot delete sale because items have been returned.'], 422);
        }

        if ($sale->created_at->diffInMinutes(now(), false) > 10) {
            return response()->json(['message' => 'Cannot delete sale because it was created more than 10 minutes ago.'], 403);
        }

        $lastClosedDate = Auth::user()->last_closed_date;
        if ($lastClosedDate && $sale->created_at->toDateString() <= $lastClosedDate) {
            return response()->json(['message' => 'Cannot delete transactions from a closed financial year.'], 403);
        }

        DB::beginTransaction();

        try {
            // Restore product stock quantities (since items are no longer sold)
            $saleItems = SaleItem::where('sale_id', $id)->get();
            foreach ($saleItems as $sItem) {
                $product = Product::find($sItem->product_id);
                if ($product) {
                    $product->stock_quantity += $sItem->quantity;
                    $product->save();
                }
            }

            // Delete associated stock movements
            \App\Models\StockMovement::where('reference_type', 'Sale')
                ->where('reference_id', $id)
                ->delete();

            $accountingService = new AccountingService(Auth::id());

            SaleItem::where('sale_id', $id)->delete();

            // Find and delete associated SalePayment
            $payment = SalePayment::where('sale_id', $id)->first();
            if ($payment) {
                $accountingService->clearEntries('SalePayment', $payment->id);
                $payment->delete();
            }

            $sale->delete();

            $accountingService->clearEntries('Sale', $id);

            DB::commit();

            return response()->json(['message' => 'Sale deleted successfully.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Failed to delete sale.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $query = Sale::query()->with(['saleItems.product', 'customer.user']);
        if (Auth::user()->role !== 'admin') {
            $query->whereHas('customer', fn($q) => $q->where('user_id', Auth::id()));
            if (session('private_ledger_unlocked') !== true) {
                $query->where(function($q) {
                    $q->where('accepted', 1)
                      ->orWhere(fn($q2) => $q2->whereNotNull('currency')->where('currency', '!=', 'INR'));
                });
            }
        }
        $sale = $query->find($id);

        if (!$sale) {
            abort(403, 'Sale not found or unauthorized access');
        }



        $allocatedPayment = 0.0;
        $returnDueDeduction = \App\Models\SaleReturnItem::where('sale_id', $sale->id)->sum('due_deduction');
        $payments = \App\Models\SalePayment::where('sale_id', $sale->id)->whereNotIn('payment_method', ['Wallet', 'Advance Deduction'])->orderBy('payment_date', 'asc')->get();

        // Keep the original down payment amount on the invoice details page as requested.

        return Inertia::render('Sale/Show', [
            'sale' => $sale,
            'allocatedPayment' => $allocatedPayment,
            'returnDueDeduction' => $returnDueDeduction,
            'payments' => $payments,
        ]);
    }
}
