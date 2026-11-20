<?php

namespace Modules\Product\Repositories\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Product\DTOs\ProductListFilters;
use Modules\Product\Models\Product;
use Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface
{
    private const ALLOWED_SORT_COLUMNS = [
        'name',
        'price',
        'created_at',
        'stock_quantity',
    ];

    public function paginateActive(ProductListFilters $filters): LengthAwarePaginator
    {
        $query = Product::query()->active();

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($filters->perPage);
    }

    public function findActiveById(int $id): ?Product
    {
        return Product::query()->active()->find($id);
    }

    public function findById(int $id): ?Product
    {
        return Product::query()->find($id);
    }

    public function lockForUpdateByIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    public function decrementStock(Product $product, int $quantity): void
    {
        $product->decrement('stock_quantity', $quantity);
    }

    public function incrementStock(Product $product, int $quantity): void
    {
        $product->increment('stock_quantity', $quantity);
    }

    private function applyFilters(Builder $query, ProductListFilters $filters): void
    {
        if ($filters->search) {
            $query->where('name', 'like', '%'.$filters->search.'%');
        }

        if ($filters->minPrice !== null) {
            $query->where('price', '>=', $filters->minPrice);
        }

        if ($filters->maxPrice !== null) {
            $query->where('price', '<=', $filters->maxPrice);
        }

        if ($filters->available === true) {
            $query->inStock();
        }

        if ($filters->available === false) {
            $query->where('stock_quantity', '<=', 0);
        }
    }

    private function applySorting(Builder $query, ProductListFilters $filters): void
    {
        $column = in_array($filters->sortBy, self::ALLOWED_SORT_COLUMNS, true)
            ? $filters->sortBy
            : 'created_at';

        $direction = strtolower($filters->sortDirection) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($column, $direction);
    }
}
