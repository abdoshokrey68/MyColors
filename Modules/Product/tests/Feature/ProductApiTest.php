<?php

namespace Modules\Product\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_paginated_active_products(): void
    {
        Product::factory()->count(3)->create();
        Product::factory()->inactive()->create();

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 3)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        ['id', 'name', 'sku', 'price', 'stock_quantity', 'status', 'is_available'],
                    ],
                    'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }

    public function test_can_search_products_by_name(): void
    {
        Product::factory()->create(['name' => 'Blue Hoodie']);
        Product::factory()->create(['name' => 'Red Cap']);

        $response = $this->getJson('/api/products?search=Hoodie');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.name', 'Blue Hoodie');
    }

    public function test_can_filter_products_by_price_range(): void
    {
        Product::factory()->create(['price' => 20]);
        Product::factory()->create(['price' => 50]);
        Product::factory()->create(['price' => 100]);

        $response = $this->getJson('/api/products?min_price=30&max_price=80');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.price', '50.00');
    }

    public function test_can_filter_products_by_availability(): void
    {
        Product::factory()->inStock(5)->create(['name' => 'In Stock Item']);
        Product::factory()->outOfStock()->create(['name' => 'Out Of Stock Item']);

        $response = $this->getJson('/api/products?available=1');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.name', 'In Stock Item');
    }

    public function test_can_sort_products_by_price_asc(): void
    {
        Product::factory()->create(['name' => 'Expensive', 'price' => 90]);
        Product::factory()->create(['name' => 'Cheap', 'price' => 10]);

        $response = $this->getJson('/api/products?sort_by=price&sort_dir=asc');

        $response->assertOk()
            ->assertJsonPath('data.items.0.name', 'Cheap')
            ->assertJsonPath('data.items.1.name', 'Expensive');
    }

    public function test_can_show_active_product(): void
    {
        $product = Product::factory()->create([
            'name' => 'Navy Hoodie',
            'sku' => 'HOODIE-01',
        ]);

        $response = $this->getJson('/api/products/'.$product->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Navy Hoodie')
            ->assertJsonPath('data.sku', 'HOODIE-01');
    }

    public function test_show_returns_not_found_for_missing_product(): void
    {
        $response = $this->getJson('/api/products/999');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Product not found.');
    }

    public function test_show_returns_not_found_for_inactive_product(): void
    {
        $product = Product::factory()->inactive()->create();

        $response = $this->getJson('/api/products/'.$product->id);

        $response->assertNotFound()
            ->assertJsonPath('message', 'Product not found.');
    }

    public function test_index_validation_rejects_invalid_sort(): void
    {
        $response = $this->getJson('/api/products?sort_by=invalid');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sort_by']);
    }
}
