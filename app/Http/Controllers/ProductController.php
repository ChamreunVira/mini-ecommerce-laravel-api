<?php

namespace App\Http\Controllers;

use App\Exceptions\ResourceNotFoundException;
use App\Helpers\ApiResponse;
use App\Http\Resources\PaginationResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'sort_by' => ['sometimes', 'string', Rule::in(['created_at', 'price', 'quantity', 'name'])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100']
        ]);

        $sortBy = $data['sort_by'] ?? "created_at";
        $direction = $data['direction'] ?? "asc";
        $limit = $data['limit'] ?? 10;

        $products = Product::orderBy($sortBy, $direction)->paginate($limit);

        return [
            'success' => true,
            'message' => "Success to retreive Product.",
            'data' => ProductResource::collection($products),
            'meta' => new PaginationResource($products)
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:30',
            'price' => 'required',
            'quantity' => 'required',
            'image' => 'required|image|mimes:png,jpg,webp,jepg',
            'category_id' => 'required|exists:categories,id'
        ]);

        $path = $request->file('image')->store('images', 'public');

        $product = Product::create([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'quantity' => $validated['quantity'],
            'image' => $path,
            'category_id' => $validated['category_id']
        ]);

        return ApiResponse::success(
            new ProductResource($product),
            "Created product successfully.",
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return ApiResponse::success(new ProductResource($product));
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required',
            'description' => 'required',
            'price' => 'required',
            'quantity' => 'required',
            'category_id' => 'required|exists:categories,id'
        ]);

        $product->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'quantity' => $validated['quantity'],
            'category_id' => $validated['category_id']
        ]);

        return ApiResponse::success(new ProductResource($product));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {

        if (!$product) {
            throw new ResourceNotFoundException("Product not found .");
        }

        $product->delete();

        return ApiResponse::success(null, "Product was deleted successfully.");
    }

    public  function generateSlug(string $slug): string
    {
        return strtolower(str_replace(' ', '-', $slug));
    }
}
