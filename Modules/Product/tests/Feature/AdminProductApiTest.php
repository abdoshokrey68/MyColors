<?php

namespace Modules\Product\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    public function test_admin_can_update_and_delete_product(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['sku' => 'TMP-01']);

        $this->patchJson('/api/admin/products/'.$product->id, [
            'price' => 55.5,
        ])->assertOk()
            ->assertJsonPath('data.price', '55.50');

        $this->deleteJson('/api/admin/products/'.$product->id)
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
