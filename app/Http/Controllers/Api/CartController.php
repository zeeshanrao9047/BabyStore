<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Products;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()
            ->cartItems()
            ->with('product:id,name,slug,price,stock,image,is_active')
            ->orderByDesc('id')
            ->get();

        $subtotalCents = 0;

        foreach ($items as $item) {
            $unitPriceCents = (int) round(
                (float) $item->product->price * 100
            );

            $lineTotalCents = $unitPriceCents * $item->quantity;
            $subtotalCents += $lineTotalCents;

            $item->setAttribute(
                'line_total',
                number_format($lineTotalCents / 100, 2, '.', '')
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart fetched successfully.',
            'data' => [
                'items' => $items,
                'subtotal' => number_format(
                    $subtotalCents / 100,
                    2,
                    '.',
                    ''
                ),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item = DB::transaction(function () use ($request, $validated) {
            $product = Products::query()
                ->where('is_active', true)
                ->lockForUpdate()
                ->findOrFail($validated['product_id']);

            $item = $request->user()
                ->cartItems()
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            $newQuantity = ($item?->quantity ?? 0)
                + $validated['quantity'];

            if ($newQuantity > $product->stock) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        "Only {$product->stock} units are currently in stock.",
                    ],
                ]);
            }

            if ($item) {
                $item->quantity = $newQuantity;
                $item->save();

                return $item;
            }

            return $request->user()->cartItems()->create([
                'product_id' => $product->id,
                'quantity' => $newQuantity,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart successfully.',
            'data' => $item->load('product'),
        ], 201);
    }

    public function update(
        Request $request,
        int $cartItem
    ): JsonResponse {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item = DB::transaction(function () use (
            $request,
            $cartItem,
            $validated
        ) {
            $item = CartItem::query()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->findOrFail($cartItem);

            $product = Products::query()
                ->where('is_active', true)
                ->lockForUpdate()
                ->findOrFail($item->product_id);

            if ($validated['quantity'] > $product->stock) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        "Only {$product->stock} units are currently in stock.",
                    ],
                ]);
            }

            $item->update([
                'quantity' => $validated['quantity'],
            ]);

            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => 'Cart quantity updated successfully.',
            'data' => $item->load('product'),
        ]);
    }

    public function destroy(
        Request $request,
        int $cartItem
    ): JsonResponse {
        $item = $request->user()
            ->cartItems()
            ->findOrFail($cartItem);

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart item removed successfully.',
        ]);
    }
}