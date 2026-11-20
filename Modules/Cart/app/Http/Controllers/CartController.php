<?php

namespace Modules\Cart\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cart\Http\Requests\StoreCartItemRequest;
use Modules\Cart\Http\Requests\UpdateCartItemRequest;
use Modules\Cart\Http\Resources\CartResource;
use Modules\Cart\Services\CartService;

class CartController extends BaseController
{
    public function __construct(
        private readonly CartService $carts,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->carts->getCart($request->user());

        return $this->success(
            new CartResource($cart),
            'Cart retrieved successfully.'
        );
    }

    public function storeItem(StoreCartItemRequest $request): JsonResponse
    {
        $cart = $this->carts->addItem(
            $request->user(),
            (int) $request->validated('product_id'),
            (int) $request->validated('quantity'),
        );

        return $this->success(
            new CartResource($cart),
            'Item added to cart.',
            201
        );
    }

    public function updateItem(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $cart = $this->carts->updateItem(
            $request->user(),
            $id,
            (int) $request->validated('quantity'),
        );

        return $this->success(
            new CartResource($cart),
            'Cart item updated.'
        );
    }

    public function destroyItem(Request $request, int $id): JsonResponse
    {
        $cart = $this->carts->removeItem($request->user(), $id);

        return $this->success(
            new CartResource($cart),
            'Cart item removed.'
        );
    }
}
