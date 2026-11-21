<?php

namespace Modules\Order\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Product\Models\Product;
use Tests\TestCase;

class CheckoutCartLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_checkout_after_commit_sees_empty_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(10)->create(['price' => 20]);

        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout')->assertCreated();

        $this->postJson('/api/checkout')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Empty cart.');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
    }
}
