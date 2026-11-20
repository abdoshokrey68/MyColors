<?php

namespace Modules\Product\DTOs;

readonly class ProductListFilters
{
    public function __construct(
        public ?string $search = null,
        public ?float $minPrice = null,
        public ?float $maxPrice = null,
        public ?bool $available = null,
        public string $sortBy = 'created_at',
        public string $sortDirection = 'desc',
        public int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            minPrice: isset($data['min_price']) ? (float) $data['min_price'] : null,
            maxPrice: isset($data['max_price']) ? (float) $data['max_price'] : null,
            available: array_key_exists('available', $data)
                ? filter_var($data['available'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_dir'] ?? 'desc',
            perPage: (int) ($data['per_page'] ?? 15),
        );
    }
}
