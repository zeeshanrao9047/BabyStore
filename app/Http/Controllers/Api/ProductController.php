<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Products;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
class ProductController extends Controller
{
 public function index(Request $request): JsonResponse
{
    // 1. Get validated/request inputs (no $validated variable was defined)
    $sortBy = $request->input('sort_by', 'created_at');
    $sortOrder = $request->input('sort_order', 'desc');
    $perPage = (int) $request->input('per_page', 10);

    // 2. Whitelist sort columns/orders to prevent SQL injection
    $allowedSortColumns = ['id', 'name', 'price', 'created_at', 'updated_at'];
    $allowedSortOrders = ['asc', 'desc'];

    if (!in_array($sortBy, $allowedSortColumns, true)) {
        $sortBy = 'created_at';
    }
    if (!in_array(strtolower($sortOrder), $allowedSortOrders, true)) {
        $sortOrder = 'desc';
    }

    // 3. Build a SINGLE query (the previous code had two conflicting $products assignments
    //    and referenced an undefined $query variable)
    $products = Products::where('is_active', true)
        ->orderBy($sortBy, $sortOrder)
        ->paginate($perPage);

    // 4. Return properly-formed JSON (missing closing brackets/parentheses fixed)
    return response()->json([
        'success' => true,
        'message' => 'Products fetched successfully.',
        'data' => ProductResource::collection($products->items()),
        'pagination' => [
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
        ],
    ]);
}


public function show(int $id): \Illuminate\Http\JsonResponse
{
    $product = Products::where('is_active', true)
        ->findOrFail($id);

    return response()->json([
        'success' => true,
        'message' => 'Product fetched successfully.',
        'data' => new ProductResource($product),
    ]);
}


public function store(StoreProductRequest $request): JsonResponse
{


   $product = Products::create($request->validated());

    return response()->json([
        'success' => true,
        'message' => 'Product created successfully.',
       'data' => new ProductResource($product),
    ], 201);
}


public function update(UpdateProductRequest $request, int $id): JsonResponse
{
    $product = Products::findOrFail($id);

   

   $product->update($request->validated());

    return response()->json([
        'success' => true,
        'message' => 'Product updated successfully.',
        'data' => new ProductResource($product->fresh()),
    ]);
}

public function destroy(int $id): JsonResponse
{
    $product = Products::findOrFail($id);

    $product->delete();

    return response()->json([
        'success' => true,
        'message' => 'Product deleted successfully.',
    ]);
}


}
