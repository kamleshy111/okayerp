<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\Category;

class ProductController extends Controller
{
    public function index(){

        $userId = Auth::id();

        $data = Product::select('products.*','categories.id as categoryId','categories.name as categoryName')
                            ->leftJoin('categories','categories.id', '=', 'products.category_id')
                            ->where('products.user_id', $userId)
                            ->get();

        $products = $data->map(function($item, $index) {

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'cgst' => $item->cgst,
                'sgst' => $item->sgst,
                'stockQuantity' => $item->stock_quantity ?? '',
                'categoryName' => $item->categoryName ?? '----',
                'unit_type' => $item->unit_type,
            ];

        });

        return Inertia::render('Product/Product',[
            'products' => $products, 
        ]);
    }

    public function create(){

        $userId = Auth::id();
  
        $categories = Category::where('user_id', $userId)->select('id', 'name')->get();

        $unitTypes = config('units.types');

        return Inertia::render('Product/Create',[
            'categories' => $categories,
            'unitTypes' => $unitTypes
        ]);
    }

    public function store(Request $request){

        $validated = $request->validate([
            'name' => 'required',
        ], [
         
            'name.required' => 'Name is required.',
        ]);

        if (!$validated) {
            // Return validation errors as a JSON response
            return response()->json(["message" => $validated]);
        }

        $randomNumber = rand(10000, 99999);
        $incrementedNumber = $randomNumber + 1;

        // Create a new Product
        Product::create([
            'user_id' => Auth::id(),
            'name' => $request->input('name'),
            'category_id' => $request->input('category_id'),
            'unit_type' => $request->input('unit_type') ?? '',
            'sgst' => $request->input('sgst') ?? 0,
            'cgst' => $request->input('cgst') ?? 0,
            'hsn_code' => $request->input('hsn_code'),
            'price' => $request->input('price') ?? 0.00,
            'sku' => $incrementedNumber,
            'description' => $request->input('description'),
  
        ]);

        return response()->json(['message' => 'Product added successfully!']);
    }

    public function edit($id){

        $data = Product::find($id);
        if (!$data) {
            return response()->json(["message" => 'Product not found.']);
        }

        $userId = Auth::id();

        $categories = Category::select('id', 'name')->where('user_id', $userId)->get();

        $unitTypes = config('units.types');

        $productDetail = [
            'id'   => $data->id ?? 0,
            'name' => $data->name ?? '',
            'unit_type' => $data->unit_type ?? '',
            'cgst' => $data->cgst ?? 0,
            'sgst' => $data->sgst ?? 0,
            'hsn_code' => $data->hsn_code ?? '',
            'price' => $data->price ?? 0.00,
            'category_id' => $data->category_id ?? '',
            'description' => $data->description ?? '',
        ];

        return Inertia::render('Product/Edit',[
            'productDetail' => $productDetail,
            'categories' => $categories,
            'unitTypes' => $unitTypes,
        ]);
    }

    public function update(Request $request, $id){

        $validated = $request->validate([
            'name' => 'required',
        ], [
         
            'name.required' => 'Name is required.',
        ]);

        if (!$validated) {
            return response()->json(["message" => $validated]);
        }

        $product = Product::where('id',$id)->first();
        if($product){
            $product->name = $request->input("name");
            $product->unit_type = $request->input("unit_type");
            $product->sgst = $request->input("sgst");
            $product->cgst = $request->input("cgst");
            $product->hsn_code = $request->input("hsn_code");
            $product->price = $request->input("price") ?? 0.00;
            $product->category_id = $request->input("category_id");
            $product->description = $request->input("description");
            $product->save();

            return response()->json(['message' => 'Product updated successfully.']);
        }else{
            return response()->json(['message' => 'Product not found.'], 404);
        }

    }

    public function destroy($id)
    {
        $product = Product::find($id);
    
        if($product) {
            $product->delete();
            return response()->json(['message' => 'Product deleted successfully.'], 200);
        }
    
        return response()->json(['message' => 'Product not found.'], 404);
    }
}