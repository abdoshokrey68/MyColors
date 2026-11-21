<?php

namespace Modules\Product\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Modules\Product\DTOs\ProductListFilters;
use Modules\Product\Exceptions\ProductNotFoundException;
use Modules\Product\Models\Product;
use Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

class ProductService
{
    private const LIST_VERSION_KEY = 'products:list:version';

    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function list(ProductListFilters $filters): LengthAwarePaginator
    {
        $version = Cache::get(self::LIST_VERSION_KEY, 1);

        $cacheKey = 'products:list:'.$version.':'.md5(json_encode([
            'search' => $filters->search,
            'min' => $filters->minPrice,
            'max' => $filters->maxPrice,
            'available' => $filters->available,
            'sort_by' => $filters->sortBy,
            'sort_dir' => $filters->sortDirection,
            'per_page' => $filters->perPage,
            'page' => request()->integer('page', 1),
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($filters) {
            return $this->products->paginateActive($filters);
        });
    }

    public function getActiveById(int $id): Product
    {
        $product = $this->products->findActiveById($id);

        if (! $product) {
            throw new ProductNotFoundException;
        }

        return $product;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        $product = $this->products->create($data);
        $this->flushListingCache();

        return $product;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Product
    {
        $product = $this->products->findById($id);

        if (! $product) {
            throw new ProductNotFoundException;
        }

        $product = $this->products->update($product, $data);
        $this->flushListingCache();

        return $product;
    }

    public function delete(int $id): void
    {
        $product = $this->products->findById($id);

        if (! $product) {
            throw new ProductNotFoundException;
        }

        $this->products->delete($product);
        $this->flushListingCache();
    }

    public function listAllForAdmin(int $perPage = 15): LengthAwarePaginator
    {
        return $this->products->paginateAll($perPage);
    }

    public function flushListingCache(): void
    {
        Cache::forever(self::LIST_VERSION_KEY, now()->timestamp);
    }
}
