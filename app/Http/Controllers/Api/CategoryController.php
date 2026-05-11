<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('services')->orderBy('name')->get();

        return response()->json([
            'success'    => true,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);

        $category = Category::create([
            'name'        => trim($request->name),
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        AdminLog::record(
            $request->user(), 'create', 'category', $category->id,
            "Creó la categoría \"{$category->name}\""
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Categoría creada correctamente.',
            'category' => $category,
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);

        $before = $category->only('name', 'description', 'is_active');

        $category->update([
            'name'        => trim($request->name),
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active', $category->is_active),
        ]);

        AdminLog::record(
            $request->user(), 'update', 'category', $category->id,
            "Actualizó la categoría \"{$category->name}\"",
            ['before' => $before, 'after' => $category->only('name', 'description', 'is_active')]
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Categoría actualizada correctamente.',
            'category' => $category->fresh(),
        ]);
    }

    public function destroy(Request $request, Category $category)
    {
        // Evitar eliminar si tiene servicios asociados activos
        $activeServices = $category->services()->where('is_active', true)->count();
        if ($activeServices > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar: la categoría tiene {$activeServices} servicio(s) activo(s). Desactívalos primero.",
            ], 422);
        }

        AdminLog::record(
            $request->user(), 'delete', 'category', $category->id,
            "Eliminó la categoría \"{$category->name}\""
        );

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada correctamente.',
        ]);
    }
}
