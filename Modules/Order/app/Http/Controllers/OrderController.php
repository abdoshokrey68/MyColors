<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Services\OrderService;

class OrderController extends BaseController
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->orders->listForUser(
            $request->user(),
            (int) $request->integer('per_page', 15),
        );

        return $this->success([
            'items' => OrderResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Orders retrieved successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->orders->getForUser($request->user(), $id);

        return $this->success(
            new OrderResource($order),
            'Order retrieved successfully.'
        );
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $this->orders->cancel($request->user(), $id);

        return $this->success(
            new OrderResource($order),
            'Order cancelled successfully.'
        );
    }
}
