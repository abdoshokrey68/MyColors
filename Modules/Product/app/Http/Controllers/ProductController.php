<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Modules\Product\DTOs\ProductListFilters;
use Modules\Product\Http\Requests\ProductIndexRequest;
use Modules\Product\Http\Resources\ProductResource;
use Modules\Product\Services\ProductService;

class ProductController extends BaseController
{
    public function __construct(
        private readonly ProductService $products,
    ) {}

    public function index(ProductIndexRequest $request): JsonResponse
    {
        $paginator = $this->products->list(
            ProductListFilters::fromArray($request->validated())
        );

        return $this->success([
            'items' => ProductResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Products retrieved successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->products->getActiveById($id);

        return $this->success(
            new ProductResource($product),
            'Product retrieved successfully.'
        );
    }
}
