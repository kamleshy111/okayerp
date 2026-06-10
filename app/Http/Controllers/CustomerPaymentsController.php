<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Customer;
use App\Models\SalePayment;

class CustomerPaymentsController extends Controller
{
    public function index(){

        // $customers = Customer::select('customers.id','customers.name','customers.email','customers.phone','sale_payments.amount','sale_payments.payment_date', 'sale_payments.payment_method')
        // ->rightJoin('sale_payments','customers.id', '=', 'sale_payments.customer_id')
        // ->get();
        $userId = Auth::id();

        $query = SalePayment::whereHas('customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
        if (session('private_ledger_unlocked') !== true) {
            $query->where('accepted', 1);
        }

        $customers = $query->with('customer')
        ->orderBy('payment_date', 'desc')
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->customer->id ?? '',
                'user_id' => $item->customer->user_id ?? '',
                'name' => $item->customer->name ?? '',
                'email' => $item->customer->email ?? '',
                'phone' => $item->customer->phone ?? '',
                'amount' => $item->amount,
                'payment_date' => $item->payment_date,
                'payment_method' => $item->payment_method,
            ];
        });

        return Inertia::render('CustomerPayment/Index',[
            'customers' => $customers,
        ]);
    }

    public function create(){

        $userId = Auth::id();
  
        $customers = Customer::where('user_id', $userId)->select('id','name')->get();

        return Inertia::render('CustomerPayment/Create',[
            'customers' => $customers
        ]);
    }

    public function store(Request $request){

        $validated = $request->validate([
            'customer_id' => 'required',
            'amount'    => 'required',
            'payment_date'    => 'required',
            'payment_method'    => 'required',
        ], [
            'customer_id.required' => 'Customer Name is required.',
            'amount.required' => 'Amount is required.',

            'payment_date.required' => 'Date is required.',
            'payment_method.required' => 'Payment Method is required.',
        ]);

        if (!$validated) {
            return response()->json(["message" => $validated]);
        }

        $userId = Auth::id();
        $customerExists = Customer::where('user_id', $userId)->where('id', $request->input('customer_id'))->exists();
        if (!$customerExists) {
            return response()->json(['message' => 'Selected customer is invalid or unauthorized.'], 403);
        }

        // Create a new Sale Payment
        $salePayment = SalePayment::create([
            'customer_id' => $request->input('customer_id'),
            'amount' => $request->input('amount') ?? '',
            'payment_date' => $request->input('payment_date') ?? 0,
            'payment_method' => $request->input('payment_method') ?? 0,
            'note' => $request->input('note'),
            'accepted' => session('private_ledger_unlocked') === true ? 0 : 1,
        ]);

        $accountingService = new \App\Services\AccountingService($userId);
        $accountingService->postCustomerPayment($salePayment);

        return response()->json(['message' => 'Customer Payments added successfully!']);
    }
}
