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
                    

        return Inertia::render('Purchase/Index',[
            'purchases' => $purchases,
        ]);
    }

    public function create(){

        $userId = Auth::id();

        $products = Product::where('user_id', $userId)->get();
        $suppliers = Supplier::where('user_id', $userId)->get();
        return Inertia::render('Purchase/Create',[
            'suppliers' => $suppliers,
            'products' => $products,
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

        // Begin a database transaction
        DB::beginTransaction();

        try {

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

        $products = Product::where('user_id', $userId)->get();
        $suppliers = Supplier::where('user_id', $userId)->get();

        return Inertia::render('Purchase/Edit',[
            'products' => $products,
            'suppliers' => $suppliers,
            'productItems' => $productItems,
            'purchases' => $purchases,
            'allocatedPayment' => $allocatedPayment,
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
        $purchaseExists = $query->where('id', $id)->exists();

        if (!$purchaseExists) {
            return response()->json(['message' => 'Purchase not found or unauthorized access.'], 403);
        }
        
        try {

            DB::transaction(function () use ($request, $id, $userId) {

                $query = Purchase::whereHas('supplier', fn($q) => $q->where('user_id', $userId));
                if (session('private_ledger_unlocked') !== true) {
                    $query->where('accepted', 1);
                }
                $purchases = $query->where('id', $id)->first();

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

    public function downloadInvoice(Request $request, $id)
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

        $allocatedPayment = round($allocatedPayment, 2);
        $returnDueDeduction = \App\Models\PurchaseReturn::where('purchase_id', $purchase->id)->sum('due_deduction');

        $pdf = Pdf::loadView('purchase_invoice', compact('purchase', 'allocatedPayment', 'returnDueDeduction'))->setPaper('a4');
        return $pdf->stream("purchase_invoice_{$purchase->id}.pdf");
    }

    public function scan(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240'
        ]);

        $userId = Auth::id();
        $suppliers = Supplier::where('user_id', $userId)->get(['id', 'name'])->toArray();
        $products = Product::where('user_id', $userId)->get(['id', 'name', 'unit_type', 'price', 'cgst', 'sgst'])->toArray();

        $apiKey = env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            $existingSupplier = Supplier::where('user_id', $userId)->first();
            $existingProduct = Product::where('user_id', $userId)->first();

            $supplierId = $existingSupplier ? $existingSupplier->id : null;
            $supplierName = $existingSupplier ? $existingSupplier->name : 'Demo Supplier Corp';

            $productId = $existingProduct ? $existingProduct->id : null;
            $productName = $existingProduct ? $existingProduct->name : 'Premium Widgets';

            return response()->json([
                'success' => true,
                'mocked' => true,
                'invoice' => [
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplierName,
                    'invoice_no' => 'INV-' . rand(10000, 99999),
                    'purchase_date' => now()->toDateString(),
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
                'suppliers' => $suppliers,
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

        $prompt = 'You are an AI assistant that extracts purchase invoice details. Please parse the uploaded invoice and return a JSON object exactly matching this structure:
        {
            "supplier_id": null,
            "supplier_name": "Extracted Name",
            "invoice_no": "Extracted Invoice No",
            "purchase_date": "YYYY-MM-DD",
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
        
        Available Suppliers (JSON): ' . json_encode($suppliers) . '
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

        // Do a sanity fallback match in PHP just in case Gemini didn't map the IDs properly
        if (isset($parsedInvoice['supplier_name']) && empty($parsedInvoice['supplier_id'])) {
            foreach ($suppliers as $s) {
                if (strcasecmp(trim($s['name']), trim($parsedInvoice['supplier_name'])) === 0) {
                    $parsedInvoice['supplier_id'] = $s['id'];
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
            'suppliers' => $suppliers,
            'products' => $products
        ]);
    }

    public function storeScanned(Request $request)
    {
        $userId = Auth::id();

        // Begin a database transaction
        DB::beginTransaction();

        try {
            $supplierId = $request->input('supplier_id');

            // 1. Create Supplier if not exists
            if (empty($supplierId)) {
                $supplierName = $request->input('supplier_name') ?: 'Scanned Supplier ' . rand(100, 999);
                // Check if supplier name already exists for this user to avoid duplication
                $existingSupplier = Supplier::where('user_id', $userId)
                    ->where('name', $supplierName)
                    ->first();

                if ($existingSupplier) {
                    $supplierId = $existingSupplier->id;
                } else {
                    $supplier = Supplier::create([
                        'user_id' => $userId,
                        'name' => $supplierName,
                        'email' => $request->input('supplier_email') ?: 'scanned@example.com',
                        'phone' => $request->input('supplier_phone') ?: '0000000000',
                        'address' => $request->input('supplier_address') ?: 'Scanned Address',
                    ]);
                    $supplierId = $supplier->id;
                }
            }

            // 2. Create the Purchase
            $purchase = Purchase::create([
                'supplier_id' => $supplierId,
                'invoice_no' => $request->input('invoice_no') ?: 'SCN-' . strtoupper(\Illuminate\Support\Str::random(6)),
                'purchase_date' => $request->input('purchase_date') ?: now()->toDateString(),
                'transport_amount' => $request->input('transport') ?? 0,
                'grand_total' => $request->input('grand_total') ?? 0.00,
                'total_amount' => $request->input('total_amount') ?? 0.00,
                'gst_amount' => $request->input('GstAmount') ?? 0.00,
                'accepted' => 1, // Directly accept scanned purchase
                'paid'  => $request->input('paid') ?? 0.00,
                'payment_method' => $request->input('payment_method') ?: "Cash",
                'payment_status' => $request->input('payment_status') ?: "Unpaid",
            ]);

            // 3. Insert purchase items and update stock quantity
            foreach ($request->input('purchase_items', []) as $item) {
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

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $productId,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_type' => $item['unit_type'] ?: 'pcs',
                    'price' => $item['price'] ?? 0,
                    'base_price' => $item['baseAmount'] ?? 0.00,
                    'sgst' => $item['sgst'] ?? 0,
                    'cgst' => $item['cgst'] ?? 0,
                ]);

                // Update Stock Quantity of product
                $product = Product::find($productId);
                if ($product) {
                    $product->stock_quantity += $item['quantity'];
                    $product->save();

                    // Log stock movement
                    \App\Models\StockMovement::create([
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'quantity' => $item['quantity'],
                        'type' => 'Addition',
                        'reference_type' => 'Purchase',
                        'reference_id' => $purchase->id,
                        'reason' => "Purchase Bill #{$purchase->id} (Scanned)",
                    ]);
                }
            }

            $accountingService = new AccountingService($userId);
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
            return response()->json(['message' => 'Purchase scanned & added successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'An error occurred while saving the purchase: ' . $e->getMessage()], 500);
        }
    }
}