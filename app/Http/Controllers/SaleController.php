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

class SaleController extends Controller
{
    public function index(){

        // $data = Sale::select('sales.*','customers.name as customerName', 'customers.email', 'customers.phone',)
        //             ->leftJoin('customers','sales.customer_id', '=', 'customers.id')
        //             ->get();

        $userId = Auth::id();
        $data = Sale::select('sales.*','customers.name as customerName','customers.email','customers.phone')
                    ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
                    ->leftJoin('users', 'customers.user_id', '=', 'users.id')
                    ->where('customers.user_id', $userId)
                    ->get();

        $sales = $data->map(function($item) {

            return [
                'id' => $item->id,
                'customerName' => $item->customerName,
                'email' => $item->email ?? '',
                'phone' => $item->phone ?? '', 
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
                'grand_total' => $request->input('grand_total') ?? 0.00,
                'total_amount' => $request->input('total_amount') ?? 0.00,
                'gst_amount' => $request->input('GstAmount') ?? 0.00,
                'accepted' => $request->input('accepted') ?? 0,
                'paid'  => $request->input('paid') ?? 0.00,
                'payment_method' => $request->input('payment_method') ?? "",
                'payment_status' => $request->input('payment_status') ?? "Unpaid",
                'discount'  => $request->input('discount') ?? 0,
            ]);

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
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'sale added successfully.',
                'invoice_url' => route('sale.invoice.download', ['id' => $sale->id]),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
    
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
                    ->where('customer_id', $id)
                    ->get();

        $totalPurchaseAmount = $sale->sum('grand_total');
        $totalPurchasePaid = $sale->sum('paid');

        $totalDirectPaid = SalePayment::whereHas('customer', fn($q) => $q->where('user_id', $userId))
                                      ->where('customer_id', $id)
                                      ->sum('amount');

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

        $sales = Sale::whereHas('customer', fn($q) => $q->where('user_id', Auth::id()))
                    ->with(['saleItems.product', 'customer'])
                    ->find($id);

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

        $userId = Auth::id();

        $products = Product::where('user_id', $userId)->get();
        $customers = Customer::where('user_id', $userId)->get();

        return Inertia::render('Sale/Edit',[
            'products' => $products,
            'customers' => $customers,
            'productItems' => $productItems,
            'sales' => $sales,   
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
        $saleExists = Sale::whereHas('customer', fn($q) => $q->where('user_id', $userId))
                          ->where('id', $id)
                          ->exists();

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

                $sale = Sale::whereHas('customer', fn($q) => $q->where('user_id', $userId))
                            ->where('id', $id)
                            ->first();

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
                ]);

                //SaleItem old get and product in update quantity
                $oldItems = SaleItem::where('sale_id', $id)->get();

                foreach ($oldItems as $oldItem) {
                    $product = Product::where('user_id', $userId)->find($oldItem->product_id);
                    if ($product) {
                        $product->stock_quantity += $oldItem->quantity;
                        $product->save();
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
                    }

                    SaleItem::create($saleItem);
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

        $sale = Sale::whereHas('customer', fn($q) => $q->where('user_id', Auth::id()))
                    ->with(['saleItems.product', 'customer'])
                    ->find($id);

        if (!$sale) {
            abort(403, 'Sale not found or unauthorized access');
        }

        $pdf = Pdf::loadView('invoice', compact('sale'))->setPaper('a4');
        return $pdf->stream("invoice_{$sale->id}.pdf");
    }

    public function destroy($id){

        $sale = Sale::whereHas('customer', fn($q) => $q->where('user_id', Auth::id()))
                    ->find($id);

        if (!$sale) {
            return response()->json(['message' => 'Sale not found.'], 404);
        }
    
        DB::beginTransaction();
    
        try {

            SaleItem::where('sale_id', $id)->delete();

            $sale->delete();
    
            DB::commit();
    
            return response()->json(['message' => 'Sale deleted successfully.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json(['message' => 'Failed to delete purchase.', 'error' => $e->getMessage()], 500);
        }
    }
}
