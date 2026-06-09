<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\SalePayment;
use App\Models\Purchase;

class PrivateSaleController extends Controller
{
    /**
     * Render the private ledger listing or PIN unlock screen.
     */
    public function index()
    {
        $user = Auth::user();
        if ($user->role !== 'store') {
            abort(403, 'Unauthorized access.');
        }

        $unlocked = session('private_ledger_unlocked') === true;

        if (!$unlocked) {
            return Inertia::render('Private/Index', [
                'unlocked' => false,
                'hasPin' => !empty($user->ledger_pin),
            ]);
        }

        $userId = Auth::id();

        // Get private sales (accepted = 0)
        $sales = Sale::whereHas('customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->where('accepted', 0)
        ->with('customer')
        ->orderBy('created_at', 'desc')
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

        // Get private payments (accepted = 0)
        $paymentsData = SalePayment::whereHas('customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->where('accepted', 0)
        ->with('customer')
        ->orderBy('payment_date', 'desc')
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->customer->name ?? '',
                'email' => $item->customer->email ?? '',
                'phone' => $item->customer->phone ?? '',
                'amount' => $item->amount,
                'payment_date' => $item->payment_date,
                'payment_method' => $item->payment_method,
                'note' => $item->note,
            ];
        });

        // Get private purchases (accepted = 0)
        $purchasesData = Purchase::whereHas('supplier', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->where('accepted', 0)
        ->with('supplier')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->id,
                'supplierName' => $item->supplier->name ?? '',
                'phone' => $item->supplier->phone ?? '',
                'email' => $item->supplier->email ?? '',
                'grand_total' => $item->grand_total,
                'purchase_date' => $item->created_at->format('d-m-Y'),
                'payment_status' => $item->payment_status,
            ];
        });

        $customers = Customer::where('user_id', $userId)->select('id','name')->get();

        return Inertia::render('Private/Index', [
            'unlocked' => true,
            'hasPin' => !empty($user->ledger_pin),
            'sales' => $sales,
            'purchases' => $purchasesData,
            'payments' => $paymentsData,
            'customers' => $customers,
        ]);
    }

    /**
     * Unlock the private ledger with the store owner PIN.
     */
    public function unlock(Request $request)
    {
        $request->validate([
            'pin' => 'required|string|digits:4',
        ]);

        $user = Auth::user();

        if (empty($user->ledger_pin)) {
            return response()->json([
                'errors' => ['pin' => ['Please set a Private Ledger PIN in your Profile Settings first.']]
            ], 422);
        }

        if (Hash::check($request->pin, $user->ledger_pin)) {
            session(['private_ledger_unlocked' => true]);
            return response()->json(['message' => 'Private ledger unlocked successfully.']);
        }

        return response()->json([
            'errors' => ['pin' => ['Invalid PIN. Access denied.']]
        ], 422);
    }

    /**
     * Lock the private ledger session.
     */
    public function lock()
    {
        session()->forget('private_ledger_unlocked');
        return redirect()->route('private.index');
    }

    /**
     * Store a private payment.
     */
    public function storePayment(Request $request)
    {
        if (session('private_ledger_unlocked') !== true) {
            return response()->json(['message' => 'Unauthorized action. Ledger is locked.'], 403);
        }

        $validated = $request->validate([
            'customer_id' => 'required',
            'amount'    => 'required|numeric|min:0.01',
            'payment_date'    => 'required|date',
            'payment_method'    => 'required|string',
            'note' => 'nullable|string',
        ], [
            'customer_id.required' => 'Customer Name is required.',
            'amount.required' => 'Amount is required.',
            'payment_date.required' => 'Date is required.',
            'payment_method.required' => 'Payment Method is required.',
        ]);

        $userId = Auth::id();
        $customerExists = Customer::where('user_id', $userId)->where('id', $request->input('customer_id'))->exists();
        if (!$customerExists) {
            return response()->json(['message' => 'Selected customer is invalid or unauthorized.'], 403);
        }

        SalePayment::create([
            'customer_id' => $request->input('customer_id'),
            'amount' => $request->input('amount'),
            'accepted' => 0, // Private ledger payment
            'payment_date' => $request->input('payment_date'),
            'payment_method' => $request->input('payment_method'),
            'note' => $request->input('note'),
        ]);

        return response()->json(['message' => 'Private payment recorded successfully.']);
    }
}
