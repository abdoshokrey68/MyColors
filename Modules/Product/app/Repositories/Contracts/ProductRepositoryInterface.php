<?php

namespace Modules\Product\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Product\DTOs\ProductListFilters;
use Modules\Product\Models\Product;

interface ProductRepositoryInterface
{
    public function paginateActive(ProductListFilters $filters): LengthAwarePaginator;

    public function findActiveById(int $id): ?Product;

    public function findById(int $id): ?Product;

    /**
     * Lock products in ascending ID order to avoid deadlocks.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    public function lockForUpdateByIds(array $ids): Collection;

    public function decrementStock(Product $product, int $quantity): void;

    public function incrementStock(Product $product, int $quantity): void;
}
