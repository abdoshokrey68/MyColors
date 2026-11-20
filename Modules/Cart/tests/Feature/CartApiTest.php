<?php

namespace Modules\Cart\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_cart(): void
    {
        $this->getJson('/api/cart')->assertUnauthorized();
        $this->postJson('/api/cart/items', [])->assertUnauthorized();
    }

    public function test_authenticated_customer_can_view_empty_cart(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/cart');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.subtotal', '0.00');
    }

    public function test_customer_can_add_item_to_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(10)->create(['price' => 25.50]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.subtotal', '51.00');

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_adding_same_product_increments_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(10)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $response = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.quantity', 5);
    }

    public function test_cannot_add_more_than_available_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(3)->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 4,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Insufficient stock. Only 3 unit(s) available.');
    }

    public function test_cannot_add_inactive_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'status' => ProductStatus::Inactive,
            'stock_quantity' => 5,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertNotFound()
            ->assertJsonPath('message', 'Product not found.');
    }

    public function test_customer_can_update_cart_item_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(10)->create(['price' => 10]);
        $item = $this->createCartItem($user, $product, 1);

        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/cart/items/'.$item->id, [
            'quantity' => 4,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.items.0.quantity', 4)
            ->assertJsonPath('data.subtotal', '40.00');
    }

    public function test_customer_can_remove_cart_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(10)->create();
        $item = $this->createCartItem($user, $product, 2);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/cart/items/'.$item->id);

        $response->assertOk()
            ->assertJsonPath('data.items_count', 0);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_customer_cannot_update_another_users_cart_item(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $product = Product::factory()->inStock(10)->create();
        $item = $this->createCartItem($owner, $product, 1);

        Sanctum::actingAs($intruder);

        $this->patchJson('/api/cart/items/'.$item->id, [
            'quantity' => 2,
        ])->assertNotFound();
    }

    private function createCartItem(User $user, Product $product, int $quantity): CartItem
    {
        $cart = Cart::query()->create(['user_id' => $user->id]);

        return CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }
}
