<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Products;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = DB::transaction(function () use (
            $request,
            $validated
        ) {
            $cartItems = CartItem::query()
                ->where('user_id', $request->user()->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['Your cart is empty.'],
                ]);
            }

            $orderLines = [];
            $subtotalCents = 0;

            foreach ($cartItems as $cartItem) {
                $product = Products::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->find($cartItem->product_id);

                if (!$product) {
                    throw ValidationException::withMessages([
                        'cart' => [
                            'A product in your cart is no longer available.',
                        ],
                    ]);
                }

                if ($product->stock < $cartItem->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => [
                            "Insufficient stock for {$product->name}.",
                        ],
                    ]);
                }

                $unitPriceCents = (int) round(
                    (float) $product->price * 100
                );

                $lineTotalCents = $unitPriceCents * $cartItem->quantity;
                $subtotalCents += $lineTotalCents;

                $orderLines[] = [
                    'product' => $product,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => number_format(
                        $unitPriceCents / 100,
                        2,
                        '.',
                        ''
                    ),
                    'quantity' => $cartItem->quantity,
                    'line_total' => number_format(
                        $lineTotalCents / 100,
                        2,
                        '.',
                        ''
                    ),
                ];
            }

            // Shipping fee filhal zero hai; delivery pricing baad mein configure hogi.
            $shippingFeeCents = 0;
            $totalCents = $subtotalCents + $shippingFeeCents;

            $order = Order::create([
                'order_number' => 'BS-'
                    . now()->format('YmdHis')
                    . '-'
                    . strtoupper(Str::random(6)),
                'user_id' => $request->user()->id,
                'status' => 'pending',
                'payment_status' => 'pending',
                'subtotal' => number_format(
                    $subtotalCents / 100,
                    2,
                    '.',
                    ''
                ),
                'shipping_fee' => number_format(
                    $shippingFeeCents / 100,
                    2,
                    '.',
                    ''
                ),
                'total' => number_format(
                    $totalCents / 100,
                    2,
                    '.',
                    ''
                ),
                'shipping_name' => $validated['shipping_name'],
                'shipping_phone' => $validated['shipping_phone'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'],
                'shipping_postal_code' =>
                    $validated['shipping_postal_code'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($orderLines as $line) {
                $line['product']->decrement(
                    'stock',
                    $line['quantity']
                );

                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'product_name' => $line['product_name'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);
            }

            CartItem::query()
                ->where('user_id', $request->user()->id)
                ->delete();

            return $order->load('items');
        });

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'data' => $order,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with('items')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Orders fetched successfully.',
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(
        Request $request,
        int $order
    ): JsonResponse {
        $order = $request->user()
            ->orders()
            ->with('items')
            ->findOrFail($order);

        return response()->json([
            'success' => true,
            'message' => 'Order fetched successfully.',
            'data' => $order,
        ]);
    }
}