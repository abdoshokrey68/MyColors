<?php

namespace Modules\Order\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdempotencyConflictException extends Exception
{
    public function __construct(string $message = 'Idempotency-Key was reused with a different request payload.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 409);
    }
}
