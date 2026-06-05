<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;

class CustomersController extends Controller
{
    public function index(){

        $userId = Auth::id();

        $customers = Customer::where('user_id', $userId)->with('sales', 'payments')->get();

        $customers = $customers->map(function ($customer) {
            $totalSaleAmount = $customer->sales->sum('grand_total');
            $totalSalePaid = $customer->sales->sum('paid');
            $totalDirectPaid = $customer->payments->sum('amount');

            $totalReceived = $totalSalePaid + $totalDirectPaid;
            $balance = $totalReceived - $totalSaleAmount;

            $dueAmount = $balance < 0 ? abs($balance) : 0;
            $advanceAmount = $balance > 0 ? $balance : 0;
            $status = $balance === 0 ? 'clear' : ($balance < 0 ? 'due' : 'advance');

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'due_amount' => $dueAmount,
                'advance_amount' => $advanceAmount,
                'status' => $status,
            ];
        });
        return Inertia::render('Customer/Customer', [
            'customers' => $customers,
        ]);

    }

    public function create(){
        
        return Inertia::render('Customer/Create');
    }

    public function store(Request $request){

        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email|max:255|unique:customers,email',
            'phone' => 'required',
        ], [
         
            'name.required' => 'Name is required.',
            'email.unique' => 'The customer email ID must be unique. Please choose a different email ID.',
            'phone.required' => 'Phone Number is required.',
        ]);

        if (!$validated) {
            // Return validation errors as a JSON response
            return response()->json(["message" => $validated]);
        }

        // Create a new Customer
        Customer::create([
            'user_id' => Auth::id(),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'status' => $request->input('status') ?? 'inactive',
        ]);

        return response()->json(['message' => 'Customer added successfully!']);
    }
    
    public function downloadInvoice($id){

        $customer = Customer::with('sales.saleItems.product')->find($id);

        $data = $customer->sales->map(function ($sale) {
                    return [
                        'sale_id' => $sale->id,
                        'discount' => $sale->discount ?? 0.00,
                        'gstAmount' => $sale->gst_amount ?? 0.00,
                        'grand_total' => $sale->grand_total ?? 0.00,
                        'sale_date' => $sale->created_at->format('Y-m-d'),
                        'items' => $sale->saleItems->map(function ($item) {
                            return [
                                'product_name' => $item->product->name ?? 'N/A',
                                'cgst' => $item->product->cgst ?? '0.00%',
                                'sgst' => $item->product->sgst ?? '0.00%',
                                'quantity' => $item->quantity,
                                'price' => $item->price,
                                'total' => $item->quantity * $item->price,
                            ];
                        }),
                    ];
                });
 
        if (!$customer) {
            abort(404, 'Customer not found.');
        }

        $pdf = Pdf::loadView('customerInvoice', compact('data','customer'))->setPaper('a4');
            return $pdf->download("invoice_customer_{$customer->id}.pdf");       
       
    }

    public function edit($id){

        $data = Customer::find($id);
        if (!$data) {
            return response()->json(["message" => 'Customer not found.']);
        }

        $customerDetail = [
            'id'   => $data->id ?? 0,
            'name' => $data->name ?? '',
            'email' => $data->email ?? '',
            'phone' => $data->phone ?? '',
            'address' => $data->address ?? '',
        ];

        return Inertia::render('Customer/Edit',[
            'customerDetail' => $customerDetail,
        ]);

    }

    public function update(Request $request, $id){

        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:customers,email,' . $id,
            'phone' => 'required',
        ], [
         
            'name.required' => 'Name is required.',
            'email.unique' => 'The customer email ID must be unique. Please choose a different email ID.',
            'phone.required' => 'Phone Number is required.',
        ]);

        if (!$validated) {
            // Return validation errors as a JSON response
            return response()->json(["message" => $validated]);
        }

        $customer = Customer::where('id',$id)->first();
        if($customer){
            $customer->name = $request->input("name");
            $customer->email = $request->input("email");
            $customer->phone = $request->input("phone");
            $customer->address = $request->input("address");
            $customer->status = $request->input("status") ?? 'inactive';
            $customer->save();

            return response()->json(['message' => 'Customer updated successfully.']);
        }else{
            return response()->json(['message' => 'Customer not found.'], 404);
        }
    }

    public function destroy($id)
    {
        $customer = Customer::find($id);
        if($customer) {
            $customer->delete();
            return response()->json(['message' => 'Customer deleted successfully.'], 200); 
        }
        return response()->json(['message' => 'Customer not found.'], 404);
    }
}
