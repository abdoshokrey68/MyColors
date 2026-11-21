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

class AdminOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipped_order_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 20]);
        $order = $this->placeOrder($user, $product, 1);
        $order->update(['status' => OrderStatus::Shipped]);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders/'.$order->id.'/cancel')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Shipped->value,
        ]);
    }

    public function test_admin_can_transition_order_status_and_rejects_invalid_transition(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 20]);
        $order = $this->placeOrder($user, $product, 1);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/orders/'.$order->id.'/status', [
            'status' => OrderStatus::Paid->value,
        ])->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Paid->value);

        $this->patchJson('/api/admin/orders/'.$order->id.'/status', [
            'status' => OrderStatus::Delivered->value,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Paid->value,
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

        return Order::query()->firstOrFail();
    }
}
