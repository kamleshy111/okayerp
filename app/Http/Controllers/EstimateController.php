<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Category;
use Barryvdh\DomPDF\Facade\Pdf;

class EstimateController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $estimates = Estimate::whereHas('customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with('customer')->orderBy('id', 'desc')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'customer_name' => $item->customer->name,
                'customer_phone' => $item->customer->phone,
                'estimate_no' => $item->estimate_no,
                'estimate_date' => $item->estimate_date,
                'expiry_date' => $item->expiry_date,
                'grand_total' => $item->grand_total,
                'status' => $item->status,
                'accepted' => $item->accepted,
            ];
        });

        return Inertia::render('Estimate/Index', [
            'estimates' => $estimates,
        ]);
    }

    public function create()
    {
        $userId = Auth::id();
        $products = [];
        $categories = Category::select('id', 'name')->where('user_id', $userId)->get();
        $unitTypes = config('units.types');
        $gstRates = \App\Models\GstRate::where('is_active', true)->get();

        return Inertia::render('Estimate/Create', [
            'products' => $products,
            'categories' => $categories,
            'unitTypes' => $unitTypes,
            'gstRates' => $gstRates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required',
            'estimate_items.*.product_id' => 'required',
        ], [
            'customer_id.required' => 'Customer name is required.',
            'estimate_items.*.product_id.required' => 'Product is required.',
        ]);

        $userId = Auth::id();

        // Validate customer belongs to logged-in user
        $customerExists = Customer::where('user_id', $userId)->where('id', $request->input('customer_id'))->exists();
        if (!$customerExists) {
            return response()->json(['message' => 'Selected customer is invalid or unauthorized.'], 403);
        }

        // Validate products belong to logged-in user
        $productIds = collect($request->input('estimate_items', []))->pluck('product_id')->unique();
        if ($productIds->isNotEmpty()) {
            $validProductsCount = Product::where('user_id', $userId)->whereIn('id', $productIds)->count();
            if ($validProductsCount !== $productIds->count()) {
                return response()->json(['message' => 'One or more selected products are invalid or unauthorized.'], 403);
            }
        }

        DB::beginTransaction();

        try {
            // Auto-generate estimate_no
            $year = date('Y');
            $latestEstimate = Estimate::whereHas('customer', fn($q) => $q->where('user_id', $userId))
                ->where('estimate_no', 'like', "EST-{$year}-%")
                ->orderBy('id', 'desc')
                ->first();

            $sequence = 1;
            if ($latestEstimate) {
                $parts = explode('-', $latestEstimate->estimate_no);
                $lastSequence = (int) end($parts);
                $sequence = $lastSequence + 1;
            }
            $estimateNo = 'EST-' . $year . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            // Create estimate
            $estimate = Estimate::create([
                'customer_id' => $request->input('customer_id'),
                'estimate_no' => $estimateNo,
                'estimate_date' => $request->input('estimate_date') ?? date('Y-m-d'),
                'expiry_date' => $request->input('expiry_date'),
                'gst_amount' => $request->input('GstAmount') ?? 0.00,
                'discount' => $request->input('discount') ?? 0.00,
                'total_amount' => $request->input('total_amount') ?? 0.00,
                'grand_total' => $request->input('grand_total') ?? 0.00,
                'status' => 'Draft',
                'accepted' => $request->input('accepted') ?? 1,
                'notes' => $request->input('notes'),
                'currency' => $request->input('currency') ?: 'INR',
                'exchange_rate' => $request->input('exchange_rate') ?: 1.0000,
            ]);

            // Create items
            foreach ($request->input('estimate_items', []) as $item) {
                EstimateItem::create([
                    'estimate_id' => $estimate->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_type' => $item['unit_type'],
                    'price' => $item['price'],
                    'base_price' => $item['baseAmount'] ?? 0.00,
                    'sgst' => $item['sgst'] ?? 0,
                    'cgst' => $item['cgst'] ?? 0,
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Quotation added successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save quotation.', 'error' => $e->getMessage()], 500);
        }
    }

    public function edit($id)
    {
        $userId = Auth::id();
        $estimate = Estimate::whereHas('customer', fn($q) => $q->where('user_id', $userId))->with('items')->find($id);

        if (!$estimate) {
            abort(404, 'Quotation not found.');
        }

        $productIds = $estimate->items->pluck('product_id')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->get();
        $customer = Customer::find($estimate->customer_id);
        $customers = $customer ? [$customer] : [];
        $categories = Category::select('id', 'name')->where('user_id', $userId)->get();
        $unitTypes = config('units.types');
        $gstRates = \App\Models\GstRate::where('is_active', true)->get();

        return Inertia::render('Estimate/Edit', [
            'estimate' => $estimate,
            'customers' => $customers,
            'products' => $products,
            'categories' => $categories,
            'unitTypes' => $unitTypes,
            'gstRates' => $gstRates,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'customer_id' => 'required',
            'estimate_items.*.product_id' => 'required',
        ], [
            'customer_id.required' => 'Customer name is required.',
            'estimate_items.*.product_id.required' => 'Product is required.',
        ]);

        $userId = Auth::id();

        // Validate customer belongs to logged-in user
        $customerExists = Customer::where('user_id', $userId)->where('id', $request->input('customer_id'))->exists();
        if (!$customerExists) {
            return response()->json(['message' => 'Selected customer is invalid or unauthorized.'], 403);
        }

        // Validate products belong to logged-in user
        $productIds = collect($request->input('estimate_items', []))->pluck('product_id')->unique();
        if ($productIds->isNotEmpty()) {
            $validProductsCount = Product::where('user_id', $userId)->whereIn('id', $productIds)->count();
            if ($validProductsCount !== $productIds->count()) {
                return response()->json(['message' => 'One or more selected products are invalid or unauthorized.'], 403);
            }
        }

        DB::beginTransaction();

        try {
            $estimate = Estimate::whereHas('customer', fn($q) => $q->where('user_id', $userId))->find($id);
            if (!$estimate) {
                return response()->json(['message' => 'Quotation not found or unauthorized.'], 404);
            }

            // Update estimate metadata
            $estimate->update([
                'customer_id' => $request->input('customer_id'),
                'estimate_date' => $request->input('estimate_date') ?? date('Y-m-d'),
                'expiry_date' => $request->input('expiry_date'),
                'gst_amount' => $request->input('GstAmount') ?? 0.00,
                'discount' => $request->input('discount') ?? 0.00,
                'total_amount' => $request->input('total_amount') ?? 0.00,
                'grand_total' => $request->input('grand_total') ?? 0.00,
                'accepted' => $request->input('accepted') ?? 1,
                'notes' => $request->input('notes'),
                'currency' => $request->input('currency') ?: 'INR',
                'exchange_rate' => $request->input('exchange_rate') ?: 1.0000,
            ]);

            // Re-sync items
            EstimateItem::where('estimate_id', $id)->delete();
            foreach ($request->input('estimate_items', []) as $item) {
                EstimateItem::create([
                    'estimate_id' => $estimate->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_type' => $item['unit_type'],
                    'price' => $item['price'],
                    'base_price' => $item['baseAmount'] ?? 0.00,
                    'sgst' => $item['sgst'] ?? 0,
                    'cgst' => $item['cgst'] ?? 0,
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Quotation updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update quotation.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getJson($id)
    {
        $userId = Auth::id();
        $estimate = Estimate::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->with(['items.product', 'customer'])
            ->find($id);

        if (!$estimate) {
            return response()->json(['message' => 'Quotation not found or unauthorized.'], 403);
        }

        return response()->json($estimate);
    }

    public function downloadPdf($id)
    {
        $query = Estimate::query()->with(['items.product', 'customer.user']);
        if (Auth::user()->role !== 'admin') {
            $query->whereHas('customer', fn($q) => $q->where('user_id', Auth::id()));
        }
        $estimate = $query->find($id);

        if (!$estimate) {
            abort(403, 'Quotation not found or unauthorized access');
        }

        $pdf = Pdf::loadView('estimateInvoice', compact('estimate'))->setPaper('a4');
        return $pdf->stream("quotation_{$estimate->estimate_no}.pdf");
    }

    public function destroy($id)
    {
        $userId = Auth::id();
        $estimate = Estimate::whereHas('customer', fn($q) => $q->where('user_id', $userId))->find($id);

        if (!$estimate) {
            return response()->json(['message' => 'Quotation not found.'], 404);
        }

        DB::beginTransaction();

        try {
            EstimateItem::where('estimate_id', $id)->delete();
            $estimate->delete();

            DB::commit();

            return response()->json(['message' => 'Quotation deleted successfully.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete quotation.', 'error' => $e->getMessage()], 500);
        }
    }
}
