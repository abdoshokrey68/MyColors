<?php

namespace Modules\Cart\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartItemNotFoundException extends Exception
{
    public function __construct(string $message = 'Cart item not found.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 404);
    }
}
