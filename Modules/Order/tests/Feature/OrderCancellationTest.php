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
use Modules\Promotion\Enums\PromotionType;
use Modules\Promotion\Models\Promotion;
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
    }

    public function test_cancel_releases_coupon_usage(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 120]);
        $promotion = Promotion::factory()->create([
            'code' => 'SUMMER20',
            'type' => PromotionType::Percentage,
            'value' => 20,
            'min_cart_amount' => 100,
            'max_discount_amount' => 50,
            'usage_limit' => 10,
            'used_count' => 0,
        ]);

        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/checkout', ['promotion_code' => 'SUMMER20'])->assertCreated();

        $order = Order::query()->firstOrFail();
        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'used_count' => 1,
        ]);
        $this->assertDatabaseHas('promotion_usages', [
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $this->postJson('/api/orders/'.$order->id.'/cancel')->assertOk();

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'used_count' => 0,
        ]);
        $this->assertDatabaseMissing('promotion_usages', [
            'order_id' => $order->id,
        ]);
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

        return Order::query()->latest('id')->firstOrFail();
    }
}
