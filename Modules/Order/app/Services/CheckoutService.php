<?php

namespace Modules\Order\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Cart\Exceptions\InsufficientStockException;
use Modules\Cart\Repositories\Contracts\CartRepositoryInterface;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Exceptions\EmptyCartException;
use Modules\Order\Models\Order;
use Modules\Order\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Exceptions\ProductNotFoundException;
use Modules\Product\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Promotion\Repositories\Contracts\PromotionRepositoryInterface;
use Modules\Promotion\Services\PromotionService;

class CheckoutService
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly ProductRepositoryInterface $products,
        private readonly OrderRepositoryInterface $orders,
        private readonly PromotionService $promotions,
        private readonly PromotionRepositoryInterface $promotionRepository,
    ) {}

    public function checkout(User $user, ?string $promotionCode = null): Order
    {
        return DB::transaction(function () use ($user, $promotionCode) {
            $cart = $this->carts->getWithItemsForUser($user);

            if ($cart->items->isEmpty()) {
                throw new EmptyCartException;
            }

            $productIds = $cart->items
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $lockedProducts = $this->products->lockForUpdateByIds($productIds);

            $subtotal = 0.0;
            $orderItems = [];

            foreach ($cart->items as $item) {
                $product = $lockedProducts->get($item->product_id);

                if (! $product || $product->status !== ProductStatus::Active) {
                    throw new ProductNotFoundException('One or more products are unavailable.');
                }

                if ($item->quantity > $product->stock_quantity) {
                    throw new InsufficientStockException(
                        "Insufficient stock for {$product->name}. Only {$product->stock_quantity} unit(s) available."
                    );
                }

                $unitPrice = (float) $product->price;
                $lineTotal = round($unitPrice * $item->quantity, 2);
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'price' => number_format($unitPrice, 2, '.', ''),
                    'quantity' => $item->quantity,
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                ];
            }

            $subtotal = round($subtotal, 2);
            $discountAmount = 0.0;
            $promotionId = null;
            $promotionCodeSnapshot = null;
            $evaluation = null;

            if ($promotionCode) {
                $evaluation = $this->promotions->evaluate($promotionCode, $user, $subtotal);
                $discountAmount = $evaluation->discountAmount;
                $promotionId = $evaluation->promotion->id;
                $promotionCodeSnapshot = $evaluation->promotion->code;
            }

            $total = max(0, round($subtotal - $discountAmount, 2));

            $order = $this->orders->createWithItems([
                'user_id' => $user->id,
                'status' => OrderStatus::Placed,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'discount_amount' => number_format($discountAmount, 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
                'promotion_id' => $promotionId,
                'promotion_code' => $promotionCodeSnapshot,
            ], $orderItems);

            foreach ($cart->items as $item) {
                $product = $lockedProducts->get($item->product_id);
                $this->products->decrementStock($product, $item->quantity);
            }

            if ($evaluation) {
                $this->promotionRepository->incrementUsedCount($evaluation->promotion);
                $this->promotionRepository->recordUsage(
                    $evaluation->promotion,
                    $user->id,
                    $order->id,
                );
            }

            $this->carts->clearItems($cart);

            return $order->load('items');
        }, 5);
    }
}
