<?php

namespace Tests\Feature;
use App\Models\Products;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $overrides = []): Products
    {
        return Products::create(array_merge([
            'name' => 'Baby Cotton Shirt',
            'slug' => 'baby-cotton-shirt',
            'description' => 'Soft cotton shirt',
            'price' => 1299,
            'stock' => 25,
            'image' => null,
            'is_active' => true,
        ], $overrides));
    }

    public function test_product_list_returns_active_products(): void
    {
        $this->createProduct();

        $this->createProduct([
            'name' => 'Inactive Shirt',
            'slug' => 'inactive-shirt',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Baby Cotton Shirt');
    }

    public function test_product_details_can_be_retrieved(): void
    {
        $product = $this->createProduct();

        $this->getJson('/api/products/' . $product->id)
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Baby Cotton Shirt');
    }

    public function test_product_can_be_created(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Baby Summer Dress',
            'slug' => 'baby-summer-dress',
            'description' => 'Light summer dress',
            'price' => 1599,
            'stock' => 20,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Baby Summer Dress');

        $this->assertDatabaseHas('products', [
            'slug' => 'baby-summer-dress',
        ]);
    }

    public function test_product_can_be_updated(): void
    {
        $product = $this->createProduct();

        $this->patchJson('/api/products/' . $product->id, [
            'price' => 1499,
            'stock' => 30,
        ])->assertOk()
          ->assertJsonPath('data.price', '1499.00');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 30,
        ]);
    }

    public function test_product_can_be_deleted(): void
    {
        $product = $this->createProduct();

        $this->deleteJson('/api/products/' . $product->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_product_creation_requires_valid_data(): void
    {
        $this->postJson('/api/products', [
            'name' => '',
            'slug' => '',
            'price' => -10,
            'stock' => -5,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors([
              'name',
              'slug',
              'price',
              'stock',
          ]);
    }
}
