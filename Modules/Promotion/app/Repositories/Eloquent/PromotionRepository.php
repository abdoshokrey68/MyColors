<?php

namespace Modules\Promotion\Repositories\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Promotion\Exceptions\CouponUsageLimitReachedException;
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

    public function lockByCode(string $code): ?Promotion
    {
        return Promotion::query()
            ->where('code', strtoupper(trim($code)))
            ->lockForUpdate()
            ->first();
    }

    public function findById(int $id): ?Promotion
    {
        return Promotion::query()->find($id);
    }

    public function countUsagesForUser(Promotion $promotion, int $userId, bool $lockForUpdate = false): int
    {
        $query = PromotionUsage::query()
            ->where('promotion_id', $promotion->id)
            ->where('user_id', $userId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->count();
    }

    public function incrementUsedCount(Promotion $promotion): void
    {
        $affected = Promotion::query()
            ->whereKey($promotion->id)
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->increment('used_count');

        if ($affected === 0) {
            throw new CouponUsageLimitReachedException;
        }

        $promotion->refresh();
    }

    public function decrementUsedCount(Promotion $promotion): void
    {
        Promotion::query()
            ->whereKey($promotion->id)
            ->where('used_count', '>', 0)
            ->decrement('used_count');

        $promotion->refresh();
    }

    public function deleteUsageForOrder(int $orderId): void
    {
        PromotionUsage::query()
            ->where('order_id', $orderId)
            ->delete();
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
