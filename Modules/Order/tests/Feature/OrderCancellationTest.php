<?php

namespace Modules\Order\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_cancel_order(): void
    {
        $this->postJson('/api/orders/1/cancel')->assertUnauthorized();
    }

    public function test_customer_can_cancel_placed_order_and_stock_is_restored(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(10)->create(['price' => 25]);
        $order = $this->placeOrder($user, $product, 3);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 7,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders/'.$order->id.'/cancel');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Cancelled->value,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }

    public function test_cancel_is_idempotent_and_does_not_double_restore_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 10]);
        $order = $this->placeOrder($user, $product, 2);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders/'.$order->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->postJson('/api/orders/'.$order->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 5,
        ]);
    }

    public function test_customer_cannot_cancel_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 10]);
        $order = $this->placeOrder($owner, $product, 1);

        Sanctum::actingAs($intruder);

        $this->postJson('/api/orders/'.$order->id.'/cancel')
            ->assertNotFound()
            ->assertJsonPath('message', 'Order not found.');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Placed->value,
        ]);
    }

    public function test_cancel_missing_order_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/orders/999/cancel')
            ->assertNotFound()
            ->assertJsonPath('message', 'Order not found.');
    }

    private function placeOrder(User $user, Product $product, int $quantity): Order
    {
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/checkout')->assertCreated();

        return Order::query()->with('items')->latest('id')->firstOrFail();
    }
}
