<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;

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
                'image' => $item->image ? '/storage/' . $item->image : null,
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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ], [
            'name.required' => 'Name is required.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, webp.',
            'image.max' => 'The image size must not exceed 2MB.',
        ]);

        if (!$validated) {
            // Return validation errors as a JSON response
            return response()->json(["message" => $validated]);
        }

        // Generate unique random SKU for this tenant
        do {
            $sku = rand(10000, 99999);
        } while (Product::where('user_id', Auth::id())->where('sku', $sku)->exists());

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $imagePath = $file->storeAs('uploads/product', $filename, 'public');
        }

        // Create a new Product
        $product = Product::create([
            'user_id' => Auth::id(),
            'name' => $request->input('name'),
            'category_id' => $request->input('category_id'),
            'unit_type' => $request->input('unit_type') ?? '',
            'sgst' => $request->input('sgst') ?? 0,
            'cgst' => $request->input('cgst') ?? 0,
            'hsn_code' => $request->input('hsn_code'),
            'price' => $request->input('price') ?? 0.00,
            'sku' => $sku,
            'description' => $request->input('description'),
            'image' => $imagePath,
        ]);

        $product->message = 'Product added successfully!';
        return response()->json($product);
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
            'image' => $data->image ? '/storage/' . $data->image : null,
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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ], [
            'name.required' => 'Name is required.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, webp.',
            'image.max' => 'The image size must not exceed 2MB.',
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

            if ($request->input('remove_image') === 'true' || $request->input('remove_image') === true) {
                if ($product->image && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }
                $product->image = null;
            } elseif ($request->hasFile('image')) {
                // Delete old image if exists
                if ($product->image && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $product->image = $file->storeAs('uploads/product', $filename, 'public');
            }

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

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ], [
            'file.required' => 'CSV file is required.',
            'file.mimes' => 'The file must be a valid CSV.',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        
        $handle = fopen($path, 'r');
        if (!$handle) {
            return response()->json(['message' => 'Unable to read the uploaded CSV file.'], 400);
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return response()->json(['message' => 'CSV file is empty.'], 400);
        }

        // Map header titles to database columns case-insensitively
        $headerMap = [];
        foreach ($headers as $index => $header) {
            $cleaned = strtolower(trim($header));
            if ($cleaned === 'name' || $cleaned === 'product name' || $cleaned === 'product_name') {
                $headerMap['name'] = $index;
            } elseif ($cleaned === 'category' || $cleaned === 'category_name' || $cleaned === 'category name' || $cleaned === 'category_id') {
                $headerMap['category'] = $index;
            } elseif ($cleaned === 'unit' || $cleaned === 'unit_type' || $cleaned === 'unit type' || $cleaned === 'unittype') {
                $headerMap['unit_type'] = $index;
            } elseif ($cleaned === 'cgst' || $cleaned === 'cgst_tax' || $cleaned === 'cgst%') {
                $headerMap['cgst'] = $index;
            } elseif ($cleaned === 'sgst' || $cleaned === 'sgst_tax' || $cleaned === 'sgst%') {
                $headerMap['sgst'] = $index;
            } elseif ($cleaned === 'hsn' || $cleaned === 'hsn_code' || $cleaned === 'hsn code') {
                $headerMap['hsn_code'] = $index;
            } elseif ($cleaned === 'description' || $cleaned === 'desc') {
                $headerMap['description'] = $index;
            }
        }

        // Validate required headers
        if (!isset($headerMap['name'])) {
            fclose($handle);
            return response()->json(['message' => 'CSV must contain a "Name" or "Product Name" column.'], 422);
        }

        $userId = Auth::id();
        $importedCount = 0;
        $errors = [];
        $rowNum = 1;

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                
                // Skip empty rows
                if (empty($row) || (count($row) === 1 && empty($row[0]))) {
                    continue;
                }

                $name = isset($headerMap['name']) && isset($row[$headerMap['name']]) ? trim($row[$headerMap['name']]) : '';
                if (empty($name)) {
                    $errors[] = "Row {$rowNum}: Product Name is empty.";
                    continue;
                }

                $categoryName = isset($headerMap['category']) && isset($row[$headerMap['category']]) ? trim($row[$headerMap['category']]) : '';
                $categoryId = null;

                if (!empty($categoryName)) {
                    // Find or create Category for this user
                    $category = \App\Models\Category::firstOrCreate(
                        ['user_id' => $userId, 'name' => $categoryName]
                    );
                    $categoryId = $category->id;
                }

                $unitType = isset($headerMap['unit_type']) && isset($row[$headerMap['unit_type']]) ? trim($row[$headerMap['unit_type']]) : '';
                $price = 0.00;
                $cgst = isset($headerMap['cgst']) && isset($row[$headerMap['cgst']]) ? floatval(trim($row[$headerMap['cgst']])) : 0;
                $sgst = isset($headerMap['sgst']) && isset($row[$headerMap['sgst']]) ? floatval(trim($row[$headerMap['sgst']])) : 0;
                $hsnCode = isset($headerMap['hsn_code']) && isset($row[$headerMap['hsn_code']]) ? trim($row[$headerMap['hsn_code']]) : '';
                $description = isset($headerMap['description']) && isset($row[$headerMap['description']]) ? trim($row[$headerMap['description']]) : '';

                // Generate unique random SKU for this tenant
                do {
                    $sku = rand(10000, 99999);
                } while (Product::where('user_id', $userId)->where('sku', $sku)->exists());

                Product::create([
                    'user_id' => $userId,
                    'name' => $name,
                    'category_id' => $categoryId,
                    'unit_type' => $unitType,
                    'sgst' => $sgst,
                    'cgst' => $cgst,
                    'hsn_code' => $hsnCode,
                    'price' => $price,
                    'sku' => $sku,
                    'description' => $description,
                ]);

                $importedCount++;
            }

            fclose($handle);

            if ($importedCount === 0 && !empty($errors)) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['message' => 'No products were imported.', 'errors' => $errors], 422);
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'message' => "Successfully imported {$importedCount} products!",
                'imported_count' => $importedCount,
                'warnings' => $errors
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            fclose($handle);
            return response()->json(['message' => 'An error occurred during import: ' . $e->getMessage()], 500);
        }
    }
}