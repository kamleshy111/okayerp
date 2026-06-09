<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;

class CustomersController extends Controller
{
    public function index(){

        $userId = Auth::id();

        $customers = Customer::where('user_id', $userId)
            ->with([
                'sales' => fn($q) => $q->where('accepted', 1),
                'payments' => fn($q) => $q->where('accepted', 1)
            ])
            ->get();

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
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->where(fn ($q) => $q->where('user_id', Auth::id()))
            ],
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
        $customer = Customer::create([
            'user_id' => Auth::id(),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'gst_number' => $request->input('gst_number'),
            'address' => $request->input('address'),
            'status' => $request->input('status') ?? 'inactive',
        ]);

        $customer->message = 'Customer added successfully!';
        return response()->json($customer);
    }
    
    public function downloadInvoice($id){

        $customer = Customer::where('user_id', Auth::id())
            ->with(['sales' => fn($q) => $q->where('accepted', 1)->with('saleItems.product')])
            ->find($id);

        if (!$customer) {
            abort(403, 'Customer not found or unauthorized access');
        }

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
                        'cgst' => number_format($item->cgst, 2),
                        'sgst' => number_format($item->sgst, 2),
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'total' => $item->quantity * $item->price,
                    ];
                }),
            ];
        });

        $pdf = Pdf::loadView('customerInvoice', compact('data','customer'))->setPaper('a4');
        return $pdf->download("invoice_customer_{$customer->id}.pdf");       
    }

    public function edit($id){

        $data = Customer::where('user_id', Auth::id())->find($id);
        if (!$data) {
            abort(403, 'Customer not found or unauthorized access');
        }

        $customerDetail = [
            'id'   => $data->id ?? 0,
            'name' => $data->name ?? '',
            'email' => $data->email ?? '',
            'phone' => $data->phone ?? '',
            'gst_number' => $data->gst_number ?? '',
            'address' => $data->address ?? '',
        ];

        return Inertia::render('Customer/Edit',[
            'customerDetail' => $customerDetail,
        ]);

    }

    public function update(Request $request, $id){

        $validated = $request->validate([
            'name' => 'required',
            'email' => [
                'required',
                'email',
                Rule::unique('customers', 'email')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('user_id', Auth::id()))
            ],
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

        $customer = Customer::where('id',$id)->where('user_id', Auth::id())->first();
        if($customer){
            $customer->name = $request->input("name");
            $customer->email = $request->input("email");
            $customer->phone = $request->input("phone");
            $customer->gst_number = $request->input("gst_number");
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
        $customer = Customer::where('user_id', Auth::id())->find($id);
        if($customer) {
            $customer->delete();
            return response()->json(['message' => 'Customer deleted successfully.'], 200); 
        }
        return response()->json(['message' => 'Customer not found.'], 404);
    }
}
