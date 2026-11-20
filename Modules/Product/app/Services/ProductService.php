<?php

namespace Modules\Product\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Product\DTOs\ProductListFilters;
use Modules\Product\Exceptions\ProductNotFoundException;
use Modules\Product\Models\Product;
use Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function list(ProductListFilters $filters): LengthAwarePaginator
    {
        return $this->products->paginateActive($filters);
    }

    public function getActiveById(int $id): Product
    {
        $product = $this->products->findActiveById($id);

        if (! $product) {
            throw new ProductNotFoundException;
        }

        return $product;
    }
}
