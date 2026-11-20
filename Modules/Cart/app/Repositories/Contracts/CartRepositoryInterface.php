<?php

namespace Modules\Cart\Repositories\Contracts;

use App\Models\User;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;

interface CartRepositoryInterface
{
    public function findOrCreateForUser(User $user): Cart;

    public function getWithItemsForUser(User $user): Cart;

    public function findItemForUser(User $user, int $itemId): ?CartItem;

    public function findItemByProduct(Cart $cart, int $productId): ?CartItem;

    public function createItem(Cart $cart, int $productId, int $quantity): CartItem;

    public function updateItemQuantity(CartItem $item, int $quantity): CartItem;

    public function deleteItem(CartItem $item): void;

    public function clearItems(Cart $cart): void;
}
