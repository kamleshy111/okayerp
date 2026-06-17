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
        $sales = Sale::whereHas('customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->where('accepted', 1)
        ->with('customer')
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->id,
                'customerName' => $item->customer->name ?? '',
                'email' => $item->customer->email ?? '',
                'phone' => $item->customer->phone ?? '',
                'grand_total' => $item->grand_total,
                'sale_date' => $item->created_at->format('d-m-Y'),
                'payment_status' => $item->payment_status,
            ];
        });

        return Inertia::render('Sale/Index',[
            'sales' => $sales,
        ]);
    }

    public function create(){

        $userId = Auth::id();

        $products = Product::where('user_id', $userId)->get();
        $customers = Customer::where('user_id', $userId)->get();
        return Inertia::render('Sale/Create',[
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    public function store(Request $request){

        $validated = $request->validate([
            'customer_id' => 'required',
            'sale_items.*.product_id' => 'required',
            
     
        ], [
            'customer_id.required' => 'Customer name is required.',
            'sale_items.*.product_id.required' => 'Product is required.',
        ]);

        if (!$validated) {
            return response()->json(["message" => $validated]);
        }

        $userId = Auth::id();

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
                'grand_total' => $request->input('grand_total') ?? 0.00,
                'total_amount' => $request->input('total_amount') ?? 0.00,
                'gst_amount' => $request->input('GstAmount') ?? 0.00,
                'accepted' => $request->input('accepted') ?? 0,
                'paid'  => $request->input('paid') ?? 0.00,
                'payment_method' => $request->input('payment_method') ?? "",
                'payment_status' => $request->input('payment_status') ?? "Unpaid",
                'discount'  => $request->input('discount') ?? 0,
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
            if (app()->environment('testing')) {
                throw $e;
            }
            return response()->json(['message' => 'An error occurred while saving the sale. Please try again.'], 500);
        }
    }

    public function payment($id)
    {
        $userId = Auth::id();
        $customer = Customer::where('user_id', $userId)->find($id);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found or unauthorized access.'], 403);
        }

        $sale = Sale::whereHas('customer', fn($q) => $q->where('user_id', $userId))
                    ->where('accepted', 1)
                    ->where('customer_id', $id)
                    ->get();

        $totalPurchaseAmount = $sale->sum('grand_total');
        $totalPurchasePaid = $sale->sum('paid');

        $paymentQuery = SalePayment::whereHas('customer', fn($q) => $q->where('user_id', $userId))
                                      ->where('customer_id', $id)
                                      ->whereNull('sale_id');
        if (session('private_ledger_unlocked') !== true) {
            $paymentQuery->where('accepted', 1);
        }
        $totalDirectPaid = $paymentQuery->sum('amount');

        $totalReceived = $totalPurchasePaid + $totalDirectPaid;

        $balance = $totalReceived - $totalPurchaseAmount;
    
        return response()->json([
            'customer_id' => $id,
            'due_amount' => $balance < 0 ? abs($balance) : 0,
            'advance_amount' => $balance > 0 ? $balance : 0,
            'status' => $balance === 0 ? 'clear' : ($balance < 0 ? 'due' : 'advance'),
        ]);
    }

    public function edit($id){

        $query = Sale::whereHas('customer', fn($q) => $q->where('user_id', Auth::id()))
                    ->with(['saleItems.product', 'customer']);
        if (session('private_ledger_unlocked') !== true) {
            $query->where('accepted', 1);
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
                'price' => $item->price,
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
            $returnDueDeduction = \App\Models\SaleReturn::where('sale_id', $s->id)->sum('due_deduction');
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

        $products = Product::where('user_id', $userId)->get();
        $customers = Customer::where('user_id', $userId)->get();

        return Inertia::render('Sale/Edit',[
            'products' => $products,
            'customers' => $customers,
            'productItems' => $productItems,
            'sales' => $sales,
            'allocatedPayment' => $allocatedPayment,
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
        $query = Sale::whereHas('customer', fn($q) => $q->where('user_id', $userId));
        if (session('private_ledger_unlocked') !== true) {
            $query->where('accepted', 1);
        }
        $saleExists = $query->where('id', $id)->exists();

        if (!$saleExists) {
            return response()->json(['message' => 'Sale not found or unauthorized access.'], 403);
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
                    'gst_amount' => $request->input('GstAmount'),
                    'accepted' => $request->input('accepted') ?? 0,
                    'grand_total' => $request->input('grand_total'),
                    'total_amount' => $request->input('total_amount'),
                    'paid'  => $request->input('paid') ?? 0.00,
                    'payment_method' => $request->input('payment_method') ?? "",
                    'payment_status' => $request->input('payment_status') ?? "Unpaid",
                    'discount'  => $request->input('discount') ?? 0,
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

                // Update or create/delete associated SalePayment
                $payment = SalePayment::where('sale_id', $sale->id)->first();
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
                'message' => 'Failed to update purchase.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadInvoice(Request $request,$id){

        $query = Sale::whereHas('customer', fn($q) => $q->where('user_id', Auth::id()))
                    ->with(['saleItems.product', 'customer.user']);
        if (session('private_ledger_unlocked') !== true) {
            $query->where('accepted', 1);
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
                $returnDueDeduction = \App\Models\SaleReturn::where('sale_id', $s->id)->sum('due_deduction');
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
        $returnDueDeduction = \App\Models\SaleReturn::where('sale_id', $sale->id)->sum('due_deduction');

        $pdf = Pdf::loadView('invoice', compact('sale', 'allocatedPayment', 'returnDueDeduction'))->setPaper('a4');
        return $pdf->stream("invoice_{$sale->id}.pdf");
    }

    public function destroy($id){

        $query = Sale::whereHas('customer', fn($q) => $q->where('user_id', Auth::id()));
        if (session('private_ledger_unlocked') !== true) {
            $query->where('accepted', 1);
        }
        $sale = $query->find($id);

        if (!$sale) {
            return response()->json(['message' => 'Sale not found.'], 404);
        }
    
        DB::beginTransaction();
    
        try {

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
    
            return response()->json(['message' => 'Failed to delete purchase.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $query = Sale::whereHas('customer', fn($q) => $q->where('user_id', Auth::id()))
                    ->with(['saleItems.product', 'customer.user']);
        if (session('private_ledger_unlocked') !== true) {
            $query->where('accepted', 1);
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
                $returnDueDeduction = \App\Models\SaleReturn::where('sale_id', $s->id)->sum('due_deduction');
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

        $returnDueDeduction = \App\Models\SaleReturn::where('sale_id', $sale->id)->sum('due_deduction');

        return Inertia::render('Sale/Show', [
            'sale' => $sale,
            'allocatedPayment' => $allocatedPayment,
            'returnDueDeduction' => $returnDueDeduction,
        ]);
    }

    public function scan(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240'
        ]);

        $userId = Auth::id();
        $customers = Customer::where('user_id', $userId)->get(['id', 'name'])->toArray();
        $products = Product::where('user_id', $userId)->get(['id', 'name', 'unit_type', 'price', 'cgst', 'sgst'])->toArray();

        $apiKey = env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            $existingCustomer = Customer::where('user_id', $userId)->first();
            $existingProduct = Product::where('user_id', $userId)->first();

            $customerId = $existingCustomer ? $existingCustomer->id : null;
            $customerName = $existingCustomer ? $existingCustomer->name : 'Demo Customer Inc';

            $productId = $existingProduct ? $existingProduct->id : null;
            $productName = $existingProduct ? $existingProduct->name : 'Premium Widgets';

            return response()->json([
                'success' => true,
                'mocked' => true,
                'invoice' => [
                    'customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'invoice_no' => 'INV-' . rand(10000, 99999),
                    'sale_date' => now()->toDateString(),
                    'transport' => 120.00,
                    'total_amount' => 1250.00,
                    'gst_amount' => 225.00,
                    'grand_total' => 1595.00,
                    'items' => [
                        [
                            'product_id' => $productId,
                            'product_name' => $productName,
                            'quantity' => 10,
                            'price' => 75.00,
                            'unit_type' => 'pcs',
                            'baseAmount' => 750.00,
                            'cgst' => 9,
                            'sgst' => 9,
                        ],
                        [
                            'product_id' => null,
                            'product_name' => 'Standard Bolts',
                            'quantity' => 25,
                            'price' => 20.00,
                            'unit_type' => 'pcs',
                            'baseAmount' => 500.00,
                            'cgst' => 9,
                            'sgst' => 9,
                        ]
                    ]
                ],
                'customers' => $customers,
                'products' => $products
            ]);
        }

        $file = $request->file('file');
        $mimeType = $file->getClientMimeType();
        if ($mimeType === 'application/pdf') {
            $mimeType = 'application/pdf';
        } else {
            $mimeType = 'image/jpeg';
        }
        $base64Data = base64_encode(file_get_contents($file->getRealPath()));

        $prompt = 'You are an AI assistant that extracts sale invoice details. Please parse the uploaded invoice and return a JSON object exactly matching this structure:
        {
            "customer_id": null,
            "customer_name": "Extracted Name",
            "invoice_no": "Extracted Invoice No",
            "sale_date": "YYYY-MM-DD",
            "transport": 0.0,
            "total_amount": 0.0,
            "gst_amount": 0.0,
            "grand_total": 0.0,
            "items": [
                {
                    "product_id": null,
                    "product_name": "Extracted Product Name",
                    "quantity": 1.0,
                    "price": 100.0,
                    "unit_type": "pcs",
                    "baseAmount": 100.0,
                    "cgst": 9.0,
                    "sgst": 9.0
                }
            ]
        }
        
        Available Customers (JSON): ' . json_encode($customers) . '
        Available Products (JSON): ' . json_encode($products);

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt],
                        [
                            "inline_data" => [
                                "mime_type" => $mimeType,
                                "data" => $base64Data
                            ]
                        ]
                    ]
                ]
            ],
            "generationConfig" => [
                "responseMimeType" => "application/json"
            ]
        ];

        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return response()->json([
                'success' => false,
                'message' => 'cURL Error: ' . $err
            ], 500);
        }

        $responseData = json_decode($response, true);

        if (isset($responseData['error'])) {
            return response()->json([
                'success' => false,
                'message' => 'Gemini API Error: ' . ($responseData['error']['message'] ?? 'Unknown error')
            ], 500);
        }

        $textResponse = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $textResponse = preg_replace('/```json\s*/', '', $textResponse);
        $textResponse = preg_replace('/```/', '', $textResponse);
        $parsedInvoice = json_decode(trim($textResponse), true);

        if (!$parsedInvoice) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse JSON output from Gemini API.'
            ], 500);
        }

        // Do a sanity fallback match in PHP just in case it didn't map the IDs properly
        if (isset($parsedInvoice['customer_name']) && empty($parsedInvoice['customer_id'])) {
            foreach ($customers as $c) {
                if (strcasecmp(trim($c['name']), trim($parsedInvoice['customer_name'])) === 0) {
                    $parsedInvoice['customer_id'] = $c['id'];
                    break;
                }
            }
        }
        if (isset($parsedInvoice['items']) && is_array($parsedInvoice['items'])) {
            foreach ($parsedInvoice['items'] as &$item) {
                if (isset($item['product_name']) && empty($item['product_id'])) {
                    foreach ($products as $p) {
                        if (strcasecmp(trim($p['name']), trim($item['product_name'])) === 0) {
                              $item['product_id'] = $p['id'];
                              $item['unit_type'] = $p['unit_type'];
                              $item['cgst'] = $p['cgst'];
                              $item['sgst'] = $p['sgst'];
                              break;
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'mocked' => false,
            'invoice' => $parsedInvoice,
            'customers' => $customers,
            'products' => $products
        ]);
    }

    public function storeScanned(Request $request)
    {
        $userId = Auth::id();

        // Begin a database transaction
        DB::beginTransaction();

        try {
            $customerId = $request->input('customer_id');

            // 1. Create Customer if not exists
            if (empty($customerId)) {
                $customerName = $request->input('customer_name') ?: 'Scanned Customer ' . rand(100, 999);
                // Check if customer name already exists for this user to avoid duplication
                $existingCustomer = Customer::where('user_id', $userId)
                    ->where('name', $customerName)
                    ->first();

                if ($existingCustomer) {
                    $customerId = $existingCustomer->id;
                } else {
                    $customer = Customer::create([
                        'user_id' => $userId,
                        'name' => $customerName,
                        'email' => $request->input('customer_email') ?: 'scanned-customer@example.com',
                        'phone' => $request->input('customer_phone') ?: '0000000000',
                        'address' => $request->input('customer_address') ?: 'Scanned Address',
                    ]);
                    $customerId = $customer->id;
                }
            }

            // 2. Create the Sale
            $sale = Sale::create([
                'customer_id' => $customerId,
                'estimate_id' => null,
                'grand_total' => $request->input('grand_total') ?? 0.00,
                'total_amount' => $request->input('total_amount') ?? 0.00,
                'gst_amount' => $request->input('GstAmount') ?? 0.00,
                'accepted' => 1, // Directly accept scanned sale
                'paid'  => $request->input('paid') ?? 0.00,
                'payment_method' => $request->input('payment_method') ?: "Cash",
                'payment_status' => $request->input('payment_status') ?: "Unpaid",
                'discount' => 0.00,
            ]);

            // 3. Insert sale items and update stock quantity
            foreach ($request->input('purchase_items', []) as $item) { // Keeping purchase_items mapping name from payload
                $productId = $item['product_id'];

                // Create Product if not exists
                if (empty($productId)) {
                    $productName = $item['product_name'] ?: 'Scanned Product ' . rand(100, 999);
                    $existingProduct = Product::where('user_id', $userId)
                        ->where('name', $productName)
                        ->first();

                    if ($existingProduct) {
                        $productId = $existingProduct->id;
                    } else {
                        $product = Product::create([
                            'user_id' => $userId,
                            'name' => $productName,
                            'sku' => 'SKU-' . strtoupper(\Illuminate\Support\Str::random(6)),
                            'price' => $item['price'] ?? 0.00,
                            'category_id' => null,
                            'unit_type' => $item['unit_type'] ?: 'pcs',
                            'cgst' => $item['cgst'] ?? 0,
                            'sgst' => $item['sgst'] ?? 0,
                            'stock_quantity' => 0,
                            'description' => 'Automatically created from invoice scan'
                        ]);
                        $productId = $product->id;
                    }
                }

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $productId,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_type' => $item['unit_type'] ?: 'pcs',
                    'price' => $item['price'] ?? 0,
                    'base_price' => $item['baseAmount'] ?? 0.00,
                    'sgst' => $item['sgst'] ?? 0,
                    'cgst' => $item['cgst'] ?? 0,
                ]);

                // Update Stock Quantity of product (Deduct for sales)
                $product = Product::find($productId);
                if ($product) {
                    $product->stock_quantity -= $item['quantity'];
                    $product->save();

                    // Log stock movement
                    \App\Models\StockMovement::create([
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'quantity' => $item['quantity'],
                        'type' => 'Deduction',
                        'reference_type' => 'Sale',
                        'reference_id' => $sale->id,
                        'reason' => "Sale Invoice #{$sale->id} (Scanned)",
                    ]);
                }
            }

            $accountingService = new AccountingService($userId);
            $accountingService->postSale($sale);

            // Create a SalePayment record if down payment is made
            if ($sale->paid > 0) {
                SalePayment::create([
                    'customer_id' => $sale->customer_id,
                    'sale_id' => $sale->id,
                    'amount' => $sale->paid,
                    'payment_date' => now()->toDateString(),
                    'payment_method' => $sale->payment_method ?: 'Cash',
                    'note' => "Payment for Sale Invoice #{$sale->id}",
                    'accepted' => $sale->accepted,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Sale scanned & added successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'An error occurred while saving the sale: ' . $e->getMessage()], 500);
        }
    }
}
