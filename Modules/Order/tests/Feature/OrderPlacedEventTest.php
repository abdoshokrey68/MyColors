<?php

namespace Modules\Order\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Order\Events\OrderPlaced;
use Modules\Product\Models\Product;
use Tests\TestCase;

class OrderPlacedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_placed_is_dispatched_once_on_successful_checkout(): void
    {
        Event::fake([OrderPlaced::class]);

        $user = User::factory()->create();
        $product = Product::factory()->inStock(5)->create(['price' => 20]);
        $this->seedCart($user, $product, 1);

        Sanctum::actingAs($user);
        $this->postJson('/api/checkout')->assertCreated();

        Event::assertDispatchedTimes(OrderPlaced::class, 1);
    }

    public function test_order_placed_is_not_dispatched_when_checkout_fails(): void
    {
        Event::fake([OrderPlaced::class]);

        $user = User::factory()->create();
        $product = Product::factory()->inStock(1)->create(['price' => 20]);
        $this->seedCart($user, $product, 2);

        Sanctum::actingAs($user);
        $this->postJson('/api/checkout')->assertUnprocessable();

        Event::assertNotDispatched(OrderPlaced::class);
    }

    private function seedCart(User $user, Product $product, int $quantity): void
    {
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }
}
