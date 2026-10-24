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
    $validated = $request->validate([
        'search' => ['nullable', 'string', 'max:100'],
        'min_price' => ['nullable', 'numeric', 'min:0'],
        'max_price' => ['nullable', 'numeric', 'min:0'],
        'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        'sort_by' => [
            'sometimes',
            'in:id,name,price,created_at,updated_at',
        ],
        'sort_order' => ['sometimes', 'in:asc,desc'],
    ]);

    if (
        isset($validated['min_price'], $validated['max_price']) &&
        $validated['max_price'] < $validated['min_price']
    ) {
        return response()->json([
            'success' => false,
            'message' => 'max_price must be greater than or equal to min_price.',
            'errors' => [
                'max_price' => [
                    'The max price must be greater than or equal to the min price.',
                ],
            ],
        ], 422);
    }

    $sortBy = $validated['sort_by'] ?? 'created_at';
    $sortOrder = $validated['sort_order'] ?? 'desc';
    $perPage = (int) ($validated['per_page'] ?? 10);

    $query = Products::query()
        ->where('is_active', true);

    if (!empty($validated['search'])) {
        $search = mb_strtolower($validated['search']);
        $term = '%' . $search . '%';

        $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
        });
    }

    if (isset($validated['min_price'])) {
        $query->where('price', '>=', $validated['min_price']);
    }

    if (isset($validated['max_price'])) {
        $query->where('price', '<=', $validated['max_price']);
    }

    $products = $query
        ->orderBy($sortBy, $sortOrder)
        ->paginate($perPage)
        ->withQueryString();

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
