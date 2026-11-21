<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Modules\Order\Http\Requests\CheckoutRequest;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Services\CheckoutService;
use Modules\Order\Services\IdempotencyService;

class CheckoutController extends BaseController
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function store(CheckoutRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey) {
            return $this->idempotency->remember(
                $request->user(),
                $idempotencyKey,
                $request,
                function () use ($request) {
                    $order = $this->checkout->checkout(
                        $request->user(),
                        $request->validated('promotion_code'),
                    );

                    $response = $this->success(
                        new OrderResource($order),
                        'Order placed successfully.',
                        201
                    );

                    return [$order, $response];
                }
            );
        }

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
