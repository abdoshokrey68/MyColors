<?php

namespace Modules\Promotion\Repositories\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Promotion\Models\Promotion;
use Modules\Promotion\Models\PromotionUsage;
use Modules\Promotion\Repositories\Contracts\PromotionRepositoryInterface;

class PromotionRepository implements PromotionRepositoryInterface
{
    public function findByCode(string $code): ?Promotion
    {
        return Promotion::query()
            ->where('code', strtoupper(trim($code)))
            ->first();
    }

    public function findById(int $id): ?Promotion
    {
        return Promotion::query()->find($id);
    }

    public function countUsagesForUser(Promotion $promotion, int $userId): int
    {
        return PromotionUsage::query()
            ->where('promotion_id', $promotion->id)
            ->where('user_id', $userId)
            ->count();
    }

    public function incrementUsedCount(Promotion $promotion): void
    {
        $promotion->increment('used_count');
    }

    public function recordUsage(Promotion $promotion, int $userId, ?int $orderId = null): void
    {
        PromotionUsage::query()->create([
            'promotion_id' => $promotion->id,
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);
    }

    public function create(array $attributes): Promotion
    {
        if (isset($attributes['code'])) {
            $attributes['code'] = strtoupper(trim($attributes['code']));
        }

        return Promotion::query()->create($attributes);
    }

    public function update(Promotion $promotion, array $attributes): Promotion
    {
        if (isset($attributes['code'])) {
            $attributes['code'] = strtoupper(trim($attributes['code']));
        }

        $promotion->update($attributes);

        return $promotion->refresh();
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }

    public function paginateAll(int $perPage = 15): LengthAwarePaginator
    {
        return Promotion::query()->latest()->paginate($perPage);
    }
}
