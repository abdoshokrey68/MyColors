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
use Modules\Promotion\Models\PromotionUsage;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_validate_coupon(): void
    {
        $this->postJson('/api/promotions/validate', [
            'code' => 'SUMMER20',
        ])->assertUnauthorized();
    }

    public function test_customer_can_validate_eligible_percentage_coupon(): void
    {
        $user = User::factory()->create();
        $this->addProductToCart($user, price: 120, quantity: 1);

        Promotion::factory()->create([
            'code' => 'SUMMER20',
            'type' => PromotionType::Percentage,
            'value' => 20,
            'min_cart_amount' => 100,
            'max_discount_amount' => 50,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/promotions/validate', [
            'code' => 'summer20',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'SUMMER20')
            ->assertJsonPath('data.subtotal', '120.00')
            ->assertJsonPath('data.discount_amount', '24.00')
            ->assertJsonPath('data.total', '96.00');
    }

    public function test_max_discount_cap_is_applied(): void
    {
        $user = User::factory()->create();
        $this->addProductToCart($user, price: 400, quantity: 1);

        Promotion::factory()->create([
            'code' => 'SUMMER20',
            'type' => PromotionType::Percentage,
            'value' => 20,
            'min_cart_amount' => 100,
            'max_discount_amount' => 50,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/promotions/validate', ['code' => 'SUMMER20'])
            ->assertOk()
            ->assertJsonPath('data.discount_amount', '50.00')
            ->assertJsonPath('data.total', '350.00');
    }

    public function test_invalid_coupon_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->addProductToCart($user, price: 120, quantity: 1);
        Sanctum::actingAs($user);

        $this->postJson('/api/promotions/validate', ['code' => 'NOPE'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid coupon.');
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->addProductToCart($user, price: 120, quantity: 1);

        Promotion::factory()->expired()->create([
            'code' => 'OLD20',
            'min_cart_amount' => 0,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/promotions/validate', ['code' => 'OLD20'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Expired coupon.');
    }

    public function test_usage_limit_reached_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->addProductToCart($user, price: 120, quantity: 1);

        Promotion::factory()->create([
            'code' => 'LIMIT1',
            'usage_limit' => 1,
            'used_count' => 1,
            'min_cart_amount' => 0,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/promotions/validate', ['code' => 'LIMIT1'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Coupon usage limit reached.');
    }

    public function test_per_customer_usage_limit_is_enforced(): void
    {
        $user = User::factory()->create();
        $this->addProductToCart($user, price: 120, quantity: 1);

        $promotion = Promotion::factory()->create([
            'code' => 'ONCE',
            'per_customer_usage_limit' => 1,
            'min_cart_amount' => 0,
        ]);

        PromotionUsage::query()->create([
            'promotion_id' => $promotion->id,
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/promotions/validate', ['code' => 'ONCE'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You have reached the usage limit for this coupon.');
    }

    public function test_minimum_cart_amount_is_enforced(): void
    {
        $user = User::factory()->create();
        $this->addProductToCart($user, price: 40, quantity: 1);

        Promotion::factory()->create([
            'code' => 'SUMMER20',
            'min_cart_amount' => 100,
            'value' => 20,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/promotions/validate', ['code' => 'SUMMER20'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cart total must be at least 100.00 to use this coupon.');
    }

    public function test_empty_cart_cannot_validate_coupon(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/promotions/validate', ['code' => 'SUMMER20'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot apply a coupon to an empty cart.');
    }

    private function addProductToCart(User $user, float $price, int $quantity): void
    {
        $product = Product::factory()->inStock(20)->create(['price' => $price]);
        $cart = Cart::query()->create(['user_id' => $user->id]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }
}
