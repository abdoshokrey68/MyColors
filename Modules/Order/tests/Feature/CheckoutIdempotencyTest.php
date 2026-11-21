<?php

namespace Modules\Order\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Order\Enums\IdempotencyKeyStatus;
use Modules\Order\Models\IdempotencyKey;
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

        $this->seedCart($user, $product, 1);

        $this->postJson('/api/checkout', ['promotion_code' => 'NONE'], $headers)
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_failed_checkout_does_not_burn_idempotency_key(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(1)->create(['price' => 20]);
        $this->seedCart($user, $product, 2);

        Sanctum::actingAs($user);

        $headers = ['Idempotency-Key' => 'checkout-key-retry'];

        $this->postJson('/api/checkout', [], $headers)
            ->assertUnprocessable();

        $this->assertDatabaseCount('idempotency_keys', 0);
        $this->assertDatabaseCount('orders', 0);

        $product->update(['stock_quantity' => 5]);

        $this->postJson('/api/checkout', [], $headers)
            ->assertCreated();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('idempotency_keys', [
            'user_id' => $user->id,
            'key' => 'checkout-key-retry',
            'status' => IdempotencyKeyStatus::Completed->value,
        ]);
    }

    public function test_processing_idempotency_key_returns_conflict(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 20]);
        $this->seedCart($user, $product, 1);

        $requestHash = hash('sha256', json_encode([
            'path' => 'api/checkout',
            'method' => 'POST',
            'payload' => [],
        ]));

        IdempotencyKey::query()->create([
            'user_id' => $user->id,
            'key' => 'checkout-in-progress',
            'request_hash' => $requestHash,
            'status' => IdempotencyKeyStatus::Processing,
            'response_code' => null,
            'response_body' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout', [], ['Idempotency-Key' => 'checkout-in-progress'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Request with this Idempotency-Key is already in progress.');

        $this->assertDatabaseCount('orders', 0);
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
