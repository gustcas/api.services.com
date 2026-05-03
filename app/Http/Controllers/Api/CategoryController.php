<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class CategoryController extends Controller
{

    public function index()
    {
        $categories = Category::with('services')->get();

        return response()->json([
            'success' => true,
            'categories' => $categories
        ]);
    }


    public function store(Request $request)
    {

        $request->validate([
            'name'=>'required'
        ]);

        $category = Category::create([

            'name'=>$request->name,
            'slug'=>Str::slug($request->name),
            'description'=>$request->description

        ]);

        return response()->json([
            'success'=>true,
            'category'=>$category
        ]);
    }


    public function update(Request $request, Category $category)
    {

        $category->update([

            'name'=>$request->name,
            'slug'=>Str::slug($request->name),
            'description'=>$request->description,
            'is_active'=>$request->is_active

        ]);

        return response()->json([
            'success'=>true
        ]);
    }


    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'success'=>true
        ]);
    }

}
