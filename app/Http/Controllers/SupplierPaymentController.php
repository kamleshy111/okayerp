<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\PurchasePayment;

class SupplierPaymentController extends Controller
{
    public function index(){

        // $suppliers = Supplier::select('suppliers.id','suppliers.name','suppliers.email','suppliers.phone','purchase_payments.amount','purchase_payments.payment_date', 'purchase_payments.payment_method')
        // ->rightJoin('purchase_payments','suppliers.id', '=', 'purchase_payments.supplier_id')
        // ->get();
        $userId = Auth::id();

        $suppliers = Supplier::select('suppliers.id','suppliers.user_id','suppliers.name','suppliers.email','suppliers.phone','purchase_payments.amount','purchase_payments.payment_date','purchase_payments.payment_method')
            ->rightJoin('users', 'suppliers.user_id', '=', 'users.id')
            ->rightJoin('purchase_payments', 'suppliers.id', '=','purchase_payments.supplier_id')
            ->where('suppliers.user_id', $userId)
            ->get();

        return Inertia::render('SupplierPayment/Index',[
            'suppliers' => $suppliers,
        ]);
    }

    public function create(){

        $userId = Auth::id();
  
        $suppliers = Supplier::where('user_id',$userId)->select('id','name')->get();

        return Inertia::render('SupplierPayment/Create',[
            'suppliers' => $suppliers
        ]);
    }

    public function store(Request $request){

        $validated = $request->validate([
            'supplier_id' => 'required',
            'amount'    => 'required',
            'payment_date'    => 'required',
            'payment_method'    => 'required',
        ], [
            'supplier_id.required' => 'Customer Name is required.',
            'amount.required' => 'Amount is required.',

            'payment_date.required' => 'Date is required.',
            'payment_method.required' => 'Payment Method is required.',
        ]);

        if (!$validated) {
            return response()->json(["message" => $validated]);
        }

        $userId = Auth::id();
        $supplierExists = Supplier::where('user_id', $userId)->where('id', $request->input('supplier_id'))->exists();
        if (!$supplierExists) {
            return response()->json(['message' => 'Selected supplier is invalid or unauthorized.'], 403);
        }

        // Create a new Purchase Payment
        PurchasePayment::create([
            'supplier_id' => $request->input('supplier_id'),
            'amount' => $request->input('amount') ?? '',
            'payment_date' => $request->input('payment_date') ?? 0,
            'payment_method' => $request->input('payment_method') ?? 0,
            'note' => $request->input('note'),
  
        ]);

        return response()->json(['message' => 'Supplier Payments added successfully!']);
    }
}
