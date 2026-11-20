<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Modules\Order\Http\Requests\CheckoutRequest;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Services\CheckoutService;

class CheckoutController extends BaseController
{
    public function __construct(
        private readonly CheckoutService $checkout,
    ) {}

    public function store(CheckoutRequest $request): JsonResponse
    {
        $order = $this->checkout->checkout(
            $request->user(),
            $request->validated('promotion_code'),
        );

        return $this->success(
            new OrderResource($order),
            'Order placed successfully.',
            201
        );
    }
}
