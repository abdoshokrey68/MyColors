<?php

namespace Modules\Product\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;
use Tests\TestCase;

class AdminProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_manage_products(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/admin/products', [])
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin access required.');
    }

    public function test_admin_can_create_product(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Admin Tee',
            'sku' => 'ADMIN-TEE-01',
            'description' => 'Created by admin',
            'price' => 39.99,
            'stock_quantity' => 12,
            'status' => ProductStatus::Active->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'ADMIN-TEE-01');

        $this->assertDatabaseHas('products', ['sku' => 'ADMIN-TEE-01']);
    }

    public function test_admin_delete_deactivates_product_even_when_in_cart(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['sku' => 'TMP-01', 'status' => ProductStatus::Active]);

        $customer = User::factory()->create();
        $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson('/api/admin/products/'.$product->id, [
            'price' => 55.5,
        ])->assertOk()
            ->assertJsonPath('data.price', '55.50');

        $this->deleteJson('/api/admin/products/'.$product->id)
            ->assertOk();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => ProductStatus::Inactive->value,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/checkout')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_admin_index_rejects_per_page_over_limit(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/products?per_page=101')
            ->assertUnprocessable();
    }
}
