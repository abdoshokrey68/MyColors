<?php

namespace Modules\Order\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Product\Models\Product;
use Tests\TestCase;

class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_with_same_idempotency_key_returns_same_order(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 20]);
        $this->seedCart($user, $product, 1);

        Sanctum::actingAs($user);

        $headers = ['Idempotency-Key' => 'checkout-key-1'];

        $first = $this->postJson('/api/checkout', [], $headers)->assertCreated();
        $second = $this->postJson('/api/checkout', [], $headers)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 4,
        ]);
    }

    public function test_reused_idempotency_key_with_different_payload_conflicts(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 20]);
        $this->seedCart($user, $product, 1);

        Sanctum::actingAs($user);

        $headers = ['Idempotency-Key' => 'checkout-key-2'];

        $this->postJson('/api/checkout', [], $headers)->assertCreated();

        // Re-add cart for a second attempt with different payload.
        $this->seedCart($user, $product, 1);

        $this->postJson('/api/checkout', ['promotion_code' => 'NONE'], $headers)
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    private function seedCart(User $user, Product $product, int $quantity): void
    {
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
        $cart->items()->delete();

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }
}
