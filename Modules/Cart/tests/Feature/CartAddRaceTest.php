<?php

namespace Modules\Cart\Tests\Feature;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Repositories\Contracts\CartRepositoryInterface;
use Modules\Cart\Services\CartService;
use Modules\Product\Models\Product;
use Modules\Product\Repositories\Contracts\ProductRepositoryInterface;
use Tests\TestCase;

class CartAddRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_item_recovers_from_unique_constraint_race(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inStock(10)->create(['price' => 15]);
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);

        $existing = CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $carts = \Mockery::mock(CartRepositoryInterface::class);
        $carts->shouldReceive('findOrCreateForUser')->once()->andReturn($cart);
        $carts->shouldReceive('findItemByProduct')->once()->withArgs(fn ($c, $id) => $id === $product->id)->andReturn(null);
        $carts->shouldReceive('createItem')
            ->once()
            ->andThrow(new UniqueConstraintViolationException(
                'sqlite',
                'insert',
                [],
                new \Exception('unique')
            ));
        $carts->shouldReceive('findItemByProduct')->once()->andReturn($existing->fresh());
        $carts->shouldReceive('updateItemQuantity')
            ->once()
            ->withArgs(fn (CartItem $item, int $qty) => $qty === 3)
            ->andReturnUsing(function (CartItem $item, int $qty) {
                $item->update(['quantity' => $qty]);

                return $item->fresh()->load('product');
            });
        $carts->shouldReceive('getWithItemsForUser')
            ->once()
            ->andReturn($cart->fresh(['items.product']));

        $service = new CartService($carts, app(ProductRepositoryInterface::class));

        $result = $service->addItem($user, $product->id, 2);

        $this->assertTrue($result->items->isNotEmpty());
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
    }
}
