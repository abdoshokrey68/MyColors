<?php

namespace Modules\Order\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Order\Models\Order;

interface OrderRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    public function createWithItems(array $attributes, array $items): Order;

    public function paginateForUser(User $user, int $perPage = 15): LengthAwarePaginator;

    public function findForUser(User $user, int $orderId): ?Order;

    public function findById(int $orderId): ?Order;

    public function lockForUpdateForUser(User $user, int $orderId): ?Order;

    public function markAsCancelled(Order $order): Order;
}
