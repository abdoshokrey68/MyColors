<?php

namespace Modules\Promotion\Repositories\Eloquent;

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
}
