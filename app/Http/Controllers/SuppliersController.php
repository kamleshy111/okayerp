<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Supplier;

class SuppliersController extends Controller
{
    public function index(){

        $userId = Auth::id();

        $suppliers = Supplier::where('user_id', $userId)->with('purchases', 'purchasePayments')->get();

        $suppliers = $suppliers->map(function ($supplier) {
            
            $totalSaleAmount = $supplier->purchases->sum('grand_total');
            $totalSalePaid = $supplier->purchases->sum('paid');
            $totalDirectPaid = $supplier->purchasePayments->sum('amount');

            $totalReceived = $totalSalePaid + $totalDirectPaid;
            $balance = $totalReceived - $totalSaleAmount;

            $dueAmount = $balance < 0 ? abs($balance) : 0;
            $advanceAmount = $balance > 0 ? $balance : 0;
            $status = $balance === 0 ? 'clear' : ($balance < 0 ? 'due' : 'advance');

            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'due_amount' => $dueAmount,
                'advance_amount' => $advanceAmount,
                'status' => $status,
            ];
        });

        return Inertia::render('Supplier/Index',[
            'suppliers' => $suppliers,
        ]);
    }

    public function create(){
        
        return Inertia::render('Supplier/Create');
    }

    public function store(Request $request){

        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email|max:255|unique:suppliers,email',
            'phone' => 'required',
        ], [
         
            'name.required' => 'Name is required.',
            'email.unique' => 'The supplier email ID must be unique. Please choose a different email ID.',
            'phone.required' => 'Phone Number is required.',
        ]);

        if (!$validated) {
            // Return validation errors as a JSON response
            return response()->json(["message" => $validated]);
        }

        // Create a new Supplier
       $supplier = Supplier::create([
            'user_id' => Auth::id(),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
        ]);

        $supplier->message = 'Supplier added successfully!';
        return response()->json($supplier);


       // return response()->json(['message' => 'Supplier added successfully!']);
    }

    public function edit($id){

        $data = Supplier::where('user_id', Auth::id())->find($id);
        if (!$data) {
            return response()->json(["message" => 'Supplier not found.']);
        }

        $supplierDetail = [
            'id'   => $data->id ?? 0,
            'name' => $data->name ?? '',
            'email' => $data->email ?? '',
            'phone' => $data->phone ?? '',
            'address' => $data->address ?? '',
        ];

        return Inertia::render('Supplier/Edit',[
            'supplierDetail' => $supplierDetail,
        ]);

     }

    public function update(Request $request, $id){

        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:suppliers,email,' . $id,
            'phone' => 'required',
        ], [
         
            'name.required' => 'Name is required.',
            'email.unique' => 'The supplier email ID must be unique. Please choose a different email ID.',
            'phone.required' => 'Phone Number is required.',
        ]);

        if (!$validated) {
            // Return validation errors as a JSON response
            return response()->json(["message" => $validated]);
        }

        $supplier = Supplier::where('id',$id)->where('user_id', Auth::id())->first();
        if($supplier){
            $supplier->name = $request->input("name");
            $supplier->email = $request->input("email");
            $supplier->phone = $request->input("phone");
            $supplier->address = $request->input("address");
            $supplier->save();

            return response()->json(['message' => 'Supplier updated successfully.']);
        }else{
            return response()->json(['message' => 'Supplier not found.'], 404);
        }
    }

    public function destroy($id)
    {
        $supplier = Supplier::where('user_id', Auth::id())->find($id);
        if($supplier) {
            $supplier->delete();
            return response()->json(['message' => 'Supplier deleted successfully.'], 200); 
        }
        return response()->json(['message' => 'Supplier not found.'], 404);
    }
}
