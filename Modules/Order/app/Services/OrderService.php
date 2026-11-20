<?php

namespace Modules\Order\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Exceptions\InvalidOrderStatusException;
use Modules\Order\Exceptions\OrderNotFoundException;
use Modules\Order\Models\Order;
use Modules\Order\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function listForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->orders->paginateForUser($user, $perPage);
    }

    public function getForUser(User $user, int $orderId): Order
    {
        $order = $this->orders->findForUser($user, $orderId);

        if (! $order) {
            throw new OrderNotFoundException;
        }

        return $order;
    }

    public function cancel(User $user, int $orderId): Order
    {
        return DB::transaction(function () use ($user, $orderId) {
            $order = $this->orders->lockForUpdateForUser($user, $orderId);

            if (! $order) {
                throw new OrderNotFoundException;
            }

            // Idempotent: repeated cancel requests are safe.
            if ($order->status === OrderStatus::Cancelled) {
                return $order;
            }

            if (! $order->status->canBeCancelled()) {
                throw new InvalidOrderStatusException(
                    'This order cannot be cancelled in its current status.'
                );
            }

            $productIds = $order->items
                ->pluck('product_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $lockedProducts = $this->products->lockForUpdateByIds($productIds);

            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = $lockedProducts->get($item->product_id);

                if ($product) {
                    $this->products->incrementStock($product, $item->quantity);
                }
            }

            return $this->orders->markAsCancelled($order);
        }, 5);
    }
}
