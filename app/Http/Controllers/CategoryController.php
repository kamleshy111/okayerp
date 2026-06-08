<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Category;
use Inertia\Inertia;


class CategoryController extends Controller
{
    public function index(){

        $userId = Auth::id();

        $categories = Category::where('user_id', $userId)->get();

        return Inertia::render('Category/Category',[
            'categories' => $categories, 
        ]);
    }

    public function create(){
        
        return Inertia::render('Category/Create');
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

        // Create a new Category
        Category::create([
            'user_id' => Auth::id(),
            'name' => $request->input('name'),
            'status' => $request->input('status') ?? 'inactive',
            'description' => $request->input('description'),
  
        ]);

        return response()->json(['message' => 'Category added successfully!']);
    }

    public function edit($id){

        $data = Category::where('user_id', Auth::id())->find($id);

        if (!$data) {
            return response()->json(["message" => 'Category not found.']);
        }

        $categoryDetail = [
            'id'   => $data->id ?? 0,
            'name' => $data->name ?? '',
            'description' => $data->description ?? '',
        ];

        return Inertia::render('Category/Edit',[
            'categoryDetail' => $categoryDetail,
        ]);

    }

    public function update(Request $request, $id){

        $category = Category::where('id',$id)->where('user_id', Auth::id())->first();
        if($category){
            
            $category->name = $request->input("name");
            $category->description = $request->input("description");
            $category->save();

            return response()->json(['message' => 'Category updated successfully.']);
        }else{
            return response()->json(['message' => 'Category not found.'], 404);
        }
    }

    public function destroy($id)
    {
        $category = Category::where('user_id', Auth::id())->find($id);
    
        if($category) {
            $category->delete();
            return response()->json(['message' => 'Category deleted successfully.'], 200); 
        }
    
        return response()->json(['message' => 'Category not found.'], 404);
    }
}
