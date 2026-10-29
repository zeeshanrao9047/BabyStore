<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_product(): void
    {
        $this->postJson('/api/products', [
            'name' => 'Test Shirt',
            'slug' => 'test-shirt',
            'price' => 1000,
            'stock' => 10,
        ])->assertUnauthorized();
    }

    public function test_customer_cannot_create_product(): void
    {
        $customer = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/products', [
                'name' => 'Test Shirt',
                'slug' => 'test-shirt',
                'price' => 1000,
                'stock' => 10,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin access required.');
    }

    public function test_customer_cannot_create_category(): void
    {
        $customer = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Baby Shirts',
                'slug' => 'baby-shirts',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin access required.');
    }

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Baby Shirts',
                'slug' => 'baby-shirts',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('categories', [
            'slug' => 'baby-shirts',
        ]);
    }
}