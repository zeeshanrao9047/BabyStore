<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Products;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $overrides = []): Products
    {
        return Products::create(array_merge([
            'name' => 'Baby Cotton Shirt',
            'slug' => 'baby-cotton-shirt',
            'description' => 'Soft cotton shirt',
            'price' => 1299,
            'stock' => 10,
            'image' => null,
            'is_active' => true,
        ], $overrides));
    }

    public function test_customer_can_add_product_to_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_customer_can_view_their_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $user->cartItems()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subtotal', '2598.00');
    }

    public function test_customer_cannot_add_more_than_available_stock(): void
    {
        $user = User::factory()->create();

        $product = $this->createProduct([
            'stock' => 2,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity' => 3,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_customer_can_place_order_from_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $user->cartItems()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'shipping_name' => 'Test Customer',
                'shipping_phone' => '03001234567',
                'shipping_address' => 'House 10, Main Street',
                'shipping_city' => 'Lahore',
                'shipping_postal_code' => '54000',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', '2598.00');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total' => '2598.00',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'line_total' => '2598.00',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 8,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
        ]);
    }

    public function test_customer_cannot_checkout_empty_cart(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'shipping_name' => 'Test Customer',
                'shipping_phone' => '03001234567',
                'shipping_address' => 'House 10, Main Street',
                'shipping_city' => 'Lahore',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart']);

        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id,
        ]);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = $owner->orders()->create([
            'order_number' => 'BS-TEST-000001',
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal' => 1000,
            'shipping_fee' => 0,
            'total' => 1000,
            'shipping_name' => 'Owner',
            'shipping_phone' => '03001234567',
            'shipping_address' => 'House 1',
            'shipping_city' => 'Lahore',
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/orders/' . $order->id)
            ->assertNotFound();
    }
}