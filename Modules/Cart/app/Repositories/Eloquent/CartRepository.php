<?php

namespace Modules\Cart\Repositories\Eloquent;

use App\Models\User;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Repositories\Contracts\CartRepositoryInterface;

class CartRepository implements CartRepositoryInterface
{
    public function findOrCreateForUser(User $user): Cart
    {
        return Cart::query()->firstOrCreate([
            'user_id' => $user->id,
        ]);
    }

    public function getWithItemsForUser(User $user): Cart
    {
        $cart = $this->findOrCreateForUser($user);

        return $cart->load(['items.product']);
    }

    public function findItemForUser(User $user, int $itemId): ?CartItem
    {
        return CartItem::query()
            ->whereKey($itemId)
            ->whereHas('cart', fn ($query) => $query->where('user_id', $user->id))
            ->with('product')
            ->first();
    }

    public function findItemByProduct(Cart $cart, int $productId): ?CartItem
    {
        return $cart->items()
            ->where('product_id', $productId)
            ->first();
    }

    public function createItem(Cart $cart, int $productId, int $quantity): CartItem
    {
        return $cart->items()->create([
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    public function updateItemQuantity(CartItem $item, int $quantity): CartItem
    {
        $item->update(['quantity' => $quantity]);

        return $item->refresh()->load('product');
    }

    public function deleteItem(CartItem $item): void
    {
        $item->delete();
    }

    public function clearItems(Cart $cart): void
    {
        $cart->items()->delete();
    }
}
