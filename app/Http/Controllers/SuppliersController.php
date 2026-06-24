<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Supplier;
use Illuminate\Validation\Rule;

class SuppliersController extends Controller
{
    public function index(){

        $userId = Auth::id();

        $suppliers = Supplier::where('user_id', $userId)
            ->with(['purchases' => function ($q) {
                if (session('private_ledger_unlocked') !== true) {
                    $q->where('accepted', 1);
                }
            }, 'purchasePayments' => function ($q) {
                if (session('private_ledger_unlocked') !== true) {
                    $q->where('accepted', 1);
                }
            }])->get();

        $suppliers = $suppliers->map(function ($supplier) {
            
            $totalSaleAmount = $supplier->purchases->sum('grand_total');
            $totalSalePaid = $supplier->purchases->sum('paid');
            $totalDirectPaid = $supplier->purchasePayments->where('purchase_id', null)->sum('amount');

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
                'gstin' => $supplier->gstin,
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
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->where(fn ($q) => $q->where('user_id', Auth::id()))
            ],
            'phone' => 'required',
            'gstin' => 'required_without:pan_number|nullable|string|max:255',
            'pan_number' => 'required_without:gstin|nullable|string|max:255',
            'cin_number' => 'nullable|string|max:255',
        ], [
         
            'name.required' => 'Name is required.',
            'email.unique' => 'The supplier email ID must be unique. Please choose a different email ID.',
            'phone.required' => 'Phone Number is required.',
            'gstin.required_without' => 'Either GSTIN or PAN Number is required.',
            'pan_number.required_without' => 'Either GSTIN or PAN Number is required.',
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
            'gstin' => $request->input('gstin'),
            'pan_number' => $request->input('pan_number'),
            'cin_number' => $request->input('cin_number'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'district' => $request->input('district'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'pin_code' => $request->input('pin_code'),
        ]);

        $supplier->message = 'Supplier added successfully!';
        return response()->json($supplier);


       // return response()->json(['message' => 'Supplier added successfully!']);
    }

    public function show($id){
        $supplier = Supplier::where('user_id', Auth::id())->find($id);
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }
        return response()->json($supplier);
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
            'gstin' => $data->gstin ?? '',
            'pan_number' => $data->pan_number ?? '',
            'cin_number' => $data->cin_number ?? '',
            'address' => $data->address ?? '',
            'city' => $data->city ?? '',
            'district' => $data->district ?? '',
            'state' => $data->state ?? '',
            'country' => $data->country ?? '',
            'pin_code' => $data->pin_code ?? '',
        ];

        return Inertia::render('Supplier/Edit',[
            'supplierDetail' => $supplierDetail,
        ]);

     }

    public function update(Request $request, $id){

        $validated = $request->validate([
            'name' => 'required',
            'email' => [
                'required',
                'email',
                Rule::unique('suppliers', 'email')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('user_id', Auth::id()))
            ],
            'phone' => 'required',
            'gstin' => 'required_without:pan_number|nullable|string|max:255',
            'pan_number' => 'required_without:gstin|nullable|string|max:255',
            'cin_number' => 'nullable|string|max:255',
        ], [
         
            'name.required' => 'Name is required.',
            'email.unique' => 'The supplier email ID must be unique. Please choose a different email ID.',
            'phone.required' => 'Phone Number is required.',
            'gstin.required_without' => 'Either GSTIN or PAN Number is required.',
            'pan_number.required_without' => 'Either GSTIN or PAN Number is required.',
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
            $supplier->gstin = $request->input("gstin");
            $supplier->pan_number = $request->input("pan_number");
            $supplier->cin_number = $request->input("cin_number");
            $supplier->address = $request->input("address");
            $supplier->city = $request->input("city");
            $supplier->district = $request->input("district");
            $supplier->state = $request->input("state");
            $supplier->country = $request->input("country");
            $supplier->pin_code = $request->input("pin_code");
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
