<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\Http\Requests\StoreProductRequest;
use Modules\Product\Http\Requests\UpdateProductRequest;
use Modules\Product\Http\Resources\ProductResource;
use Modules\Product\Services\ProductService;

class AdminProductController extends BaseController
{
    public function __construct(
        private readonly ProductService $products,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->products->listAllForAdmin(
            (int) $request->integer('per_page', 15)
        );

        return $this->success([
            'items' => ProductResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Admin products retrieved.');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->validated());

        return $this->success(
            new ProductResource($product),
            'Product created.',
            201
        );
    }

    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = $this->products->update($id, $request->validated());

        return $this->success(
            new ProductResource($product),
            'Product updated.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->products->delete($id);

        return $this->success(null, 'Product deleted.');
    }
}
