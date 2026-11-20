<?php

namespace Modules\Cart\Services;

use App\Models\User;
use Modules\Cart\Exceptions\CartItemNotFoundException;
use Modules\Cart\Exceptions\InsufficientStockException;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Repositories\Contracts\CartRepositoryInterface;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Exceptions\ProductNotFoundException;
use Modules\Product\Models\Product;
use Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

class CartService
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function getCart(User $user): Cart
    {
        return $this->carts->getWithItemsForUser($user);
    }

    public function addItem(User $user, int $productId, int $quantity): Cart
    {
        $product = $this->resolvePurchasableProduct($productId);
        $cart = $this->carts->findOrCreateForUser($user);
        $existingItem = $this->carts->findItemByProduct($cart, $product->id);

        $desiredQuantity = $quantity + ($existingItem?->quantity ?? 0);
        $this->assertSufficientStock($product, $desiredQuantity);

        if ($existingItem) {
            $this->carts->updateItemQuantity($existingItem, $desiredQuantity);
        } else {
            $this->carts->createItem($cart, $product->id, $quantity);
        }

        return $this->carts->getWithItemsForUser($user);
    }

    public function updateItem(User $user, int $itemId, int $quantity): Cart
    {
        $item = $this->resolveOwnedItem($user, $itemId);
        $product = $this->resolvePurchasableProduct($item->product_id);

        $this->assertSufficientStock($product, $quantity);
        $this->carts->updateItemQuantity($item, $quantity);

        return $this->carts->getWithItemsForUser($user);
    }

    public function removeItem(User $user, int $itemId): Cart
    {
        $item = $this->resolveOwnedItem($user, $itemId);
        $this->carts->deleteItem($item);

        return $this->carts->getWithItemsForUser($user);
    }

    private function resolveOwnedItem(User $user, int $itemId): CartItem
    {
        $item = $this->carts->findItemForUser($user, $itemId);

        if (! $item) {
            throw new CartItemNotFoundException;
        }

        return $item;
    }

    private function resolvePurchasableProduct(int $productId): Product
    {
        $product = $this->products->findActiveById($productId);

        if (! $product || $product->status !== ProductStatus::Active) {
            throw new ProductNotFoundException;
        }

        return $product;
    }

    private function assertSufficientStock(Product $product, int $quantity): void
    {
        if ($quantity > $product->stock_quantity) {
            throw new InsufficientStockException(
                "Insufficient stock. Only {$product->stock_quantity} unit(s) available."
            );
        }
    }
}
