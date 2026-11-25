<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::all();
        return ApiResponse::success($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3',
            'description' => 'sometimes|nullable|string',
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'image' => 'sometimes|nullable|image|mimes:png,jpg,webp,jpeg|max:10240',
        ]);

        $categoryData = [
            'name' => $validated['name'],
            'slug' => self::generateSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ];

        if ($request->hasFile('image')) {
            $categoryData['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($categoryData);

        return ApiResponse::success($category, "Created category successfully.", 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        return ApiResponse::success($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|min:3',
            'description' => 'sometimes|nullable|string',
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'image' => 'sometimes|nullable|image|mimes:png,jpg,webp,jpeg|max:10240',
        ]);

        $categoryData = [];

        if (isset($validated['name'])) {
            $categoryData['name'] = $validated['name'];
            $categoryData['slug'] = self::generateSlug($validated['name']);
        }

        if (array_key_exists('description', $validated)) {
            $categoryData['description'] = $validated['description'];
        }

        if (isset($validated['status'])) {
            $categoryData['status'] = $validated['status'];
        }

        if ($request->hasFile('image')) {
            $categoryData['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($categoryData);

        return ApiResponse::success($category->fresh(), "Category updated successfully.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $status = $category->status === "active" ? "inactive" : "active";
        $category->update([
            'status' => $status,
        ]);

        return response()->json($category, 200);
    }

    private static function generateSlug(string $name): string
    {
        return strtolower(str_replace(' ', '-', $name));
    }
}
