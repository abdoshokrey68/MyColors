<?php

namespace Modules\Product\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Product\DTOs\ProductListFilters;
use Modules\Product\Models\Product;

interface ProductRepositoryInterface
{
    public function paginateActive(ProductListFilters $filters): LengthAwarePaginator;

    public function findActiveById(int $id): ?Product;

    public function findById(int $id): ?Product;
}
