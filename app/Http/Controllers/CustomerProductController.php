<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\Customer;
use App\Models\Product;
use App\Models\CustomerProduct;
use Barryvdh\DomPDF\Facade\Pdf;

class CustomerProductController extends Controller
{
    public function index(Request $request)
    {
        if (!Auth::user()->allow_customer_based_pricing) {
            return redirect()->route('dashboard')->with('error', 'Customer Based Sales Price feature is disabled. Please enable it in Store Profile settings.');
        }

        $userId = Auth::id();

        $customers = Customer::where('user_id', $userId)
            ->withCount('customerProducts')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'phone', 'email', 'city', 'state']);

        $selectedCustomerId = $request->query('customer_id');
        $selectedCustomer = null;
        $customerProducts = [];

        if ($selectedCustomerId) {
            $selectedCustomer = Customer::where('user_id', $userId)->find($selectedCustomerId);
        }

        // If no customer selected yet but customers exist, default to the first customer with products or first customer
        if (!$selectedCustomer && $customers->isNotEmpty()) {
            $customerWithProducts = $customers->firstWhere('customer_products_count', '>', 0);
            $selectedCustomer = $customerWithProducts ?: $customers->first();
            $selectedCustomerId = $selectedCustomer ? $selectedCustomer->id : null;
        }

        if ($selectedCustomer) {
            $customerProducts = CustomerProduct::where('customer_products.user_id', $userId)
                ->where('customer_products.customer_id', $selectedCustomer->id)
                ->join('products', 'customer_products.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->select(
                    'customer_products.id',
                    'customer_products.customer_id',
                    'customer_products.product_id',
                    'customer_products.sale_price',
                    'customer_products.created_at',
                    'products.name as product_name',
                    'products.sku',
                    'products.price as master_price',
                    'products.unit_type',
                    'products.stock_quantity',
                    'categories.name as category_name'
                )
                ->orderBy('products.name', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'customer_id' => $item->customer_id,
                        'product_id' => $item->product_id,
                        'name' => $item->product_name,
                        'sku' => $item->sku,
                        'category_name' => $item->category_name ?? '----',
                        'master_price' => (float)($item->master_price ?? 0),
                        'sale_price' => (float)$item->sale_price,
                        'unit_type' => $item->unit_type ?? '',
                        'stock_quantity' => $item->stock_quantity ?? 0,
                        'created_at' => $item->created_at ? $item->created_at->format('d-m-Y') : '',
                    ];
                });
        }

        $masterProducts = Product::where('user_id', $userId)
            ->with('category:id,name')
            ->select('id', 'name', 'sku', 'price', 'unit_type', 'category_id', 'stock_quantity')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'price' => (float)($p->price ?? 0),
                    'unit_type' => $p->unit_type,
                    'category_name' => $p->category->name ?? '----',
                    'stock_quantity' => $p->stock_quantity ?? 0,
                ];
            });

        return Inertia::render('CustomerProduct/Index', [
            'customers' => $customers,
            'selectedCustomerId' => $selectedCustomerId ? (int)$selectedCustomerId : null,
            'selectedCustomer' => $selectedCustomer,
            'customerProducts' => $customerProducts,
            'masterProducts' => $masterProducts,
        ]);
    }

    public function getCustomerProducts($customerId)
    {
        $user = Auth::user();
        if (!$user || !$user->allow_customer_based_pricing) {
            return response()->json([]);
        }

        $userId = Auth::id();

        $customer = Customer::where('user_id', $userId)->find($customerId);
        if (!$customer) {
            return response()->json([], 404);
        }

        $records = CustomerProduct::where('customer_products.user_id', $userId)
            ->where('customer_products.customer_id', $customerId)
            ->join('products', 'customer_products.product_id', '=', 'products.id')
            ->select(
                'products.*',
                'customer_products.id as customer_product_id',
                'customer_products.sale_price as customer_sale_price',
                'products.price as master_price'
            )
            ->orderBy('products.name', 'asc')
            ->get();

        $results = $records->map(function ($item) {
            $data = $item->toArray();
            // Override 'price' with the customer-specific price for invoice autofill
            $data['price'] = (float)$item->customer_sale_price;
            $data['sale_price'] = (float)$item->customer_sale_price;
            $data['master_price'] = (float)$item->master_price;
            return $data;
        });

        return response()->json($results);
    }

    public function store(Request $request)
    {
        if (!Auth::user()->allow_customer_based_pricing) {
            return response()->json(['message' => 'Customer Based Sales Price feature is disabled.'], 403);
        }

        $userId = Auth::id();

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.sale_price' => 'required|numeric|min:0',
        ]);

        $customerId = $request->input('customer_id');

        // Ensure customer belongs to tenant
        $customerExists = Customer::where('user_id', $userId)->where('id', $customerId)->exists();
        if (!$customerExists) {
            return response()->json(['message' => 'Unauthorized customer.'], 403);
        }

        $items = $request->input('items', []);
        $savedCount = 0;

        foreach ($items as $item) {
            $productExists = Product::where('user_id', $userId)->where('id', $item['product_id'])->exists();
            if (!$productExists) {
                continue;
            }

            CustomerProduct::updateOrCreate(
                [
                    'user_id' => $userId,
                    'customer_id' => $customerId,
                    'product_id' => $item['product_id'],
                ],
                [
                    'sale_price' => $item['sale_price'],
                ]
            );
            $savedCount++;
        }

        return response()->json([
            'message' => "{$savedCount} product(s) added/updated successfully for this customer.",
            'success' => true
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->allow_customer_based_pricing) {
            return response()->json(['message' => 'Customer Based Sales Price feature is disabled.'], 403);
        }

        $userId = Auth::id();

        $request->validate([
            'sale_price' => 'required|numeric|min:0',
        ]);

        $customerProduct = CustomerProduct::where('user_id', $userId)->find($id);
        if (!$customerProduct) {
            return response()->json(['message' => 'Product assignment not found.'], 404);
        }

        $customerProduct->update([
            'sale_price' => $request->input('sale_price'),
        ]);

        return response()->json([
            'message' => 'Sale price updated successfully.',
            'success' => true,
            'sale_price' => (float)$customerProduct->sale_price,
        ]);
    }

    public function destroy($id)
    {
        if (!Auth::user()->allow_customer_based_pricing) {
            return response()->json(['message' => 'Customer Based Sales Price feature is disabled.'], 403);
        }

        $userId = Auth::id();

        $customerProduct = CustomerProduct::where('user_id', $userId)->find($id);
        if (!$customerProduct) {
            return response()->json(['message' => 'Product assignment not found.'], 404);
        }

        $customerProduct->delete();

        return response()->json([
            'message' => 'Product removed from customer successfully.',
            'success' => true
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        if (!Auth::user()->allow_customer_based_pricing) {
            return response()->json(['message' => 'Customer Based Sales Price feature is disabled.'], 403);
        }

        $userId = Auth::id();

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $ids = $request->input('ids', []);
        $deleted = CustomerProduct::where('user_id', $userId)
            ->whereIn('id', $ids)
            ->delete();

        return response()->json([
            'message' => "{$deleted} product(s) removed successfully.",
            'success' => true
        ]);
    }

    public function downloadPdf(Request $request)
    {
        if (!Auth::user()->allow_customer_based_pricing) {
            abort(403, 'Customer Based Sales Price feature is disabled.');
        }

        $userId = Auth::id();
        $store = Auth::user();
        $customerId = $request->query('customer_id');

        if ($customerId && $customerId !== 'all') {
            $customer = Customer::where('user_id', $userId)->findOrFail($customerId);

            $customerProducts = CustomerProduct::where('customer_products.user_id', $userId)
                ->where('customer_products.customer_id', $customer->id)
                ->join('products', 'customer_products.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->select(
                    'customer_products.id',
                    'customer_products.sale_price',
                    'products.name as product_name',
                    'products.sku',
                    'products.price as master_price',
                    'products.unit_type',
                    'products.stock_quantity',
                    'categories.name as category_name'
                )
                ->orderBy('products.name', 'asc')
                ->get();

            $pdf = Pdf::loadView('customer_products_report_pdf', [
                'store' => $store,
                'customer' => $customer,
                'customerProducts' => $customerProducts,
                'isAll' => false,
            ])->setPaper('a4', 'portrait');

            $cleanName = Str::slug($customer->name, '_');
            return $pdf->stream("customer_pricing_{$cleanName}.pdf");
        }

        // All customers report
        $customerProducts = CustomerProduct::where('customer_products.user_id', $userId)
            ->join('customers', 'customer_products.customer_id', '=', 'customers.id')
            ->join('products', 'customer_products.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'customer_products.id',
                'customer_products.sale_price',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'products.name as product_name',
                'products.sku',
                'products.price as master_price',
                'products.unit_type',
                'categories.name as category_name'
            )
            ->orderBy('customers.name', 'asc')
            ->orderBy('products.name', 'asc')
            ->get();

        $pdf = Pdf::loadView('customer_products_report_pdf', [
            'store' => $store,
            'customer' => null,
            'customerProducts' => $customerProducts,
            'isAll' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("all_customer_products_pricing.pdf");
    }

    public function downloadCsv(Request $request)
    {
        if (!Auth::user()->allow_customer_based_pricing) {
            abort(403, 'Customer Based Sales Price feature is disabled.');
        }

        $userId = Auth::id();
        $customerId = $request->query('customer_id');

        if ($customerId && $customerId !== 'all') {
            $customer = Customer::where('user_id', $userId)->findOrFail($customerId);

            $customerProducts = CustomerProduct::where('customer_products.user_id', $userId)
                ->where('customer_products.customer_id', $customer->id)
                ->join('products', 'customer_products.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->select(
                    'customer_products.sale_price',
                    'products.name as product_name',
                    'products.sku',
                    'products.price as master_price',
                    'products.unit_type',
                    'categories.name as category_name'
                )
                ->orderBy('products.name', 'asc')
                ->get();

            $cleanName = Str::slug($customer->name, '_');
            $fileName = "customer_pricing_{$cleanName}_" . date('Y-m-d') . ".csv";

            $headers = [
                "Content-type" => "text/csv; charset=UTF-8",
                "Content-Disposition" => "attachment; filename={$fileName}",
                "Pragma" => "no-cache",
                "Cache-Control" => "no-cache, no-store, must-revalidate",
                "Expires" => "0"
            ];

            $callback = function () use ($customerProducts, $customer) {
                $file = fopen('php://output', 'w');
                // UTF-8 BOM for Excel compatibility
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

                fputcsv($file, ["Customer Price List Report - {$customer->name}"]);
                fputcsv($file, ["Phone: " . ($customer->phone ?: 'N/A')]);
                fputcsv($file, []);
                fputcsv($file, ['S.No', 'Product Name', 'SKU', 'Category', 'Unit', 'Standard Price (INR)', 'Customer Special Price (INR)']);

                foreach ($customerProducts as $idx => $item) {
                    $stdPrice = (float)($item->master_price ?? 0);
                    $custPrice = (float)$item->sale_price;

                    fputcsv($file, [
                        $idx + 1,
                        $item->product_name,
                        $item->sku ?: '',
                        $item->category_name ?: '',
                        $item->unit_type ?: '',
                        number_format($stdPrice, 2, '.', ''),
                        number_format($custPrice, 2, '.', ''),
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        // All customers CSV
        $customerProducts = CustomerProduct::where('customer_products.user_id', $userId)
            ->join('customers', 'customer_products.customer_id', '=', 'customers.id')
            ->join('products', 'customer_products.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'customer_products.sale_price',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'products.name as product_name',
                'products.sku',
                'products.price as master_price',
                'products.unit_type',
                'categories.name as category_name'
            )
            ->orderBy('customers.name', 'asc')
            ->orderBy('products.name', 'asc')
            ->get();

        $fileName = "all_customer_pricing_" . date('Y-m-d') . ".csv";

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename={$fileName}",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($customerProducts) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ["All Customer-Specific Pricing Master Report"]);
            fputcsv($file, []);
            fputcsv($file, ['S.No', 'Customer Name', 'Phone', 'Product Name', 'SKU', 'Category', 'Unit', 'Standard Price (INR)', 'Customer Special Price (INR)']);

            foreach ($customerProducts as $idx => $item) {
                $stdPrice = (float)($item->master_price ?? 0);
                $custPrice = (float)$item->sale_price;

                fputcsv($file, [
                    $idx + 1,
                    $item->customer_name,
                    $item->customer_phone ?: '',
                    $item->product_name,
                    $item->sku ?: '',
                    $item->category_name ?: '',
                    $item->unit_type ?: '',
                    number_format($stdPrice, 2, '.', ''),
                    number_format($custPrice, 2, '.', ''),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
