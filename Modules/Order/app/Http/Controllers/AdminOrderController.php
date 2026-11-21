<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Http\Requests\UpdateOrderStatusRequest;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Services\OrderService;

class AdminOrderController extends BaseController
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $order = $this->orders->updateStatus(
            $id,
            OrderStatus::from($request->validated('status')),
        );

        return $this->success(
            new OrderResource($order),
            'Order status updated.'
        );
    }
}
