<?php

namespace Modules\Promotion\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Modules\Promotion\Http\Requests\ValidatePromotionRequest;
use Modules\Promotion\Services\PromotionService;

class PromotionController extends BaseController
{
    public function __construct(
        private readonly PromotionService $promotions,
    ) {}

    public function validateCode(ValidatePromotionRequest $request): JsonResponse
    {
        $evaluation = $this->promotions->validateForUserCart(
            $request->user(),
            $request->validated('code'),
        );

        return $this->success(
            $evaluation->toArray(),
            'Coupon is valid.'
        );
    }
}
