<?php

namespace Modules\Promotion\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponMinimumCartException extends Exception
{
    public function __construct(string $message = 'Cart total does not meet the coupon minimum amount.')
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
