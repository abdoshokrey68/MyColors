<?php

namespace Modules\Promotion\Repositories\Contracts;

use Modules\Promotion\Models\Promotion;

interface PromotionRepositoryInterface
{
    public function findByCode(string $code): ?Promotion;

    public function countUsagesForUser(Promotion $promotion, int $userId): int;

    public function incrementUsedCount(Promotion $promotion): void;

    public function recordUsage(Promotion $promotion, int $userId, ?int $orderId = null): void;
}
