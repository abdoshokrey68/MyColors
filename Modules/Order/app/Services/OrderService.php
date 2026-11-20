<?php

namespace Modules\Order\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Order\Exceptions\OrderNotFoundException;
use Modules\Order\Models\Order;
use Modules\Order\Repositories\Contracts\OrderRepositoryInterface;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
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
}
