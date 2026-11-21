<?php

namespace Modules\Promotion\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Promotion\Exceptions\PromotionNotFoundException;
use Modules\Promotion\Models\Promotion;
use Modules\Promotion\Repositories\Contracts\PromotionRepositoryInterface;

class AdminPromotionService
{
    public function __construct(
        private readonly PromotionRepositoryInterface $promotions,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->promotions->paginateAll($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Promotion
    {
        return $this->promotions->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Promotion
    {
        $promotion = $this->promotions->findById($id);

        if (! $promotion) {
            throw new PromotionNotFoundException;
        }

        return $this->promotions->update($promotion, $data);
    }

    public function delete(int $id): void
    {
        $promotion = $this->promotions->findById($id);

        if (! $promotion) {
            throw new PromotionNotFoundException;
        }

        $this->promotions->delete($promotion);
    }
}
