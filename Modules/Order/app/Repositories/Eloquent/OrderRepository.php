<?php

namespace Modules\Order\Repositories\Eloquent;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Repositories\Contracts\OrderRepositoryInterface;

class OrderRepository implements OrderRepositoryInterface
{
    public function createWithItems(array $attributes, array $items): Order
    {
        $order = Order::query()->create($attributes);
        $order->items()->createMany($items);

        return $order->load('items');
    }

    public function paginateForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->with('items')
            ->latest()
            ->paginate($perPage);
    }

    public function findForUser(User $user, int $orderId): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->with('items')
            ->find($orderId);
    }

    public function findById(int $orderId): ?Order
    {
        return Order::query()->with('items')->find($orderId);
    }

    public function lockForUpdateForUser(User $user, int $orderId): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->whereKey($orderId)
            ->with('items')
            ->lockForUpdate()
            ->first();
    }

    public function lockById(int $orderId): ?Order
    {
        return Order::query()
            ->whereKey($orderId)
            ->with('items')
            ->lockForUpdate()
            ->first();
    }

    public function markAsCancelled(Order $order): Order
    {
        return $this->updateStatus($order, OrderStatus::Cancelled);
    }

    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        $order->update(['status' => $status]);

        return $order->refresh()->load('items');
    }
}
