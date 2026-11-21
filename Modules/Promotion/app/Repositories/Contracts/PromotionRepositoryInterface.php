<?php

namespace Modules\Promotion\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Promotion\Models\Promotion;

interface PromotionRepositoryInterface
{
    public function findByCode(string $code): ?Promotion;

    public function findById(int $id): ?Promotion;

    public function countUsagesForUser(Promotion $promotion, int $userId): int;

    public function incrementUsedCount(Promotion $promotion): void;

    public function recordUsage(Promotion $promotion, int $userId, ?int $orderId = null): void;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Promotion;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Promotion $promotion, array $attributes): Promotion;

    public function delete(Promotion $promotion): void;

    public function paginateAll(int $perPage = 15): LengthAwarePaginator;
}
