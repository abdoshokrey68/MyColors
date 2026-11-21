<?php

namespace Modules\Promotion\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Product\Models\Product;
use Modules\Promotion\Enums\PromotionType;
use Modules\Promotion\Models\Promotion;
use Tests\TestCase;

class CouponConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_limit_blocks_second_customer_after_first_redeem(): void
    {
        $product = Product::factory()->inStock(20)->create(['price' => 100]);

        Promotion::factory()->create([
            'code' => 'ONCEONLY',
            'type' => PromotionType::Fixed,
            'value' => 10,
            'min_cart_amount' => 50,
            'usage_limit' => 1,
            'used_count' => 0,
            'per_customer_usage_limit' => null,
        ]);

        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        $this->seedCart($customerA, $product, 1);
        $this->seedCart($customerB, $product, 1);

        Sanctum::actingAs($customerA);
        $this->postJson('/api/checkout', ['promotion_code' => 'ONCEONLY'])
            ->assertCreated()
            ->assertJsonPath('data.discount_amount', '10.00');

        Sanctum::actingAs($customerB);
        $this->postJson('/api/checkout', ['promotion_code' => 'ONCEONLY'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('promotions', [
            'code' => 'ONCEONLY',
            'used_count' => 1,
        ]);
        $this->assertDatabaseCount('orders', 1);
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
