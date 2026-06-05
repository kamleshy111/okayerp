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

        $customers = Customer::select('customers.id','customers.user_id','customers.name','customers.email','customers.phone','sale_payments.amount','sale_payments.payment_date','sale_payments.payment_method')
            ->rightjoin('users', 'customers.user_id', '=', 'users.id')
            ->rightjoin('sale_payments', 'sale_payments.customer_id', '=','customers.id')
            ->where('customers.user_id', $userId)
            ->get();

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

        // Create a new Sale Payment
        SalePayment::create([
            'customer_id' => $request->input('customer_id'),
            'amount' => $request->input('amount') ?? '',
            'payment_date' => $request->input('payment_date') ?? 0,
            'payment_method' => $request->input('payment_method') ?? 0,
            'note' => $request->input('note'),
  
        ]);

        return response()->json(['message' => 'Customer Payments added successfully!']);
    }
}
