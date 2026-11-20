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

class CheckoutOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_checkout(): void
    {
        $this->postJson('/api/checkout')->assertUnauthorized();
    }

    public function test_cannot_checkout_with_empty_cart(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/checkout')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Empty cart.');
    }

    public function test_customer_can_checkout_and_stock_is_reduced(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(10)->create([
            'name' => 'Navy Hoodie',
            'sku' => 'HOODIE-01',
            'price' => 50,
        ]);
        $this->addToCart($user, $product, 2);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout');

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', OrderStatus::Placed->value)
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.discount_amount', '0.00')
            ->assertJsonPath('data.total', '100.00')
            ->assertJsonPath('data.items.0.product_name', 'Navy Hoodie')
            ->assertJsonPath('data.items.0.sku', 'HOODIE-01')
            ->assertJsonPath('data.items.0.price', '50.00');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
    }

    public function test_checkout_applies_promotion_and_records_usage(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 120]);
        $this->addToCart($user, $product, 1);

        Promotion::factory()->create([
            'code' => 'SUMMER20',
            'type' => PromotionType::Percentage,
            'value' => 20,
            'min_cart_amount' => 100,
            'max_discount_amount' => 50,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout', [
            'promotion_code' => 'summer20',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.promotion_code', 'SUMMER20')
            ->assertJsonPath('data.discount_amount', '24.00')
            ->assertJsonPath('data.total', '96.00');

        $this->assertDatabaseHas('promotions', [
            'code' => 'SUMMER20',
            'used_count' => 1,
        ]);
        $this->assertDatabaseHas('promotion_usages', [
            'user_id' => $user->id,
        ]);
    }

    public function test_checkout_rejects_insufficient_stock_at_purchase_time(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(2)->create(['price' => 10]);
        $this->addToCart($user, $product, 2);

        $product->update(['stock_quantity' => 1]);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 1,
        ]);
    }

    public function test_concurrent_checkouts_never_oversell_stock(): void
    {
        $product = Product::factory()->inStock(5)->create(['price' => 10]);

        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        $this->addToCart($customerA, $product, 4);
        $this->addToCart($customerB, $product, 3);

        Sanctum::actingAs($customerA);
        $responseA = $this->postJson('/api/checkout');

        Sanctum::actingAs($customerB);
        $responseB = $this->postJson('/api/checkout');

        $responseA->assertCreated();
        $responseB->assertUnprocessable();

        $product->refresh();
        $this->assertSame(1, $product->stock_quantity);
        $this->assertDatabaseCount('orders', 1);
        $this->assertGreaterThanOrEqual(0, $product->stock_quantity);
    }

    public function test_order_item_keeps_price_snapshot_after_product_price_change(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(5)->create([
            'name' => 'Cap',
            'sku' => 'CAP-01',
            'price' => 20,
        ]);
        $this->addToCart($user, $product, 1);

        Sanctum::actingAs($user);
        $this->postJson('/api/checkout')->assertCreated();

        $product->update(['price' => 99, 'name' => 'New Cap Name']);

        $order = Order::query()->with('items')->first();

        $this->assertSame('20.00', $order->items->first()->price);
        $this->assertSame('Cap', $order->items->first()->product_name);
        $this->assertSame('CAP-01', $order->items->first()->sku);
    }

    public function test_customer_can_list_and_view_own_orders(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 30]);
        $this->addToCart($user, $product, 1);

        Sanctum::actingAs($user);
        $this->postJson('/api/checkout')->assertCreated();

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.total', '30.00');

        $orderId = Order::query()->first()->id;

        $this->getJson('/api/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);
    }

    public function test_customer_cannot_view_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 15]);
        $this->addToCart($owner, $product, 1);

        Sanctum::actingAs($owner);
        $this->postJson('/api/checkout')->assertCreated();
        $orderId = Order::query()->first()->id;

        Sanctum::actingAs($intruder);
        $this->getJson('/api/orders/'.$orderId)
            ->assertNotFound()
            ->assertJsonPath('message', 'Order not found.');
    }

    private function addToCart(User $user, Product $product, int $quantity): void
    {
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }
}
