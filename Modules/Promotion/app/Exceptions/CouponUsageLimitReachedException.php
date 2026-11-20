<?php

namespace Modules\Promotion\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponUsageLimitReachedException extends Exception
{
    public function __construct(string $message = 'Coupon usage limit reached.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}
