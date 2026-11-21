<?php

namespace Modules\Promotion\Services;

use App\Models\User;
use App\Support\Money;
use Modules\Cart\Services\CartService;
use Modules\Promotion\DTOs\PromotionEvaluation;
use Modules\Promotion\Enums\PromotionType;
use Modules\Promotion\Exceptions\CouponMinimumCartException;
use Modules\Promotion\Exceptions\CouponUsageLimitReachedException;
use Modules\Promotion\Exceptions\ExpiredCouponException;
use Modules\Promotion\Exceptions\InvalidCouponException;
use Modules\Promotion\Models\Promotion;
use Modules\Promotion\Repositories\Contracts\PromotionRepositoryInterface;

class PromotionService
{
    public function __construct(
        private readonly PromotionRepositoryInterface $promotions,
        private readonly CartService $carts,
    ) {}

    public function validateForUserCart(User $user, string $code): PromotionEvaluation
    {
        $cart = $this->carts->getCart($user);

        if ($cart->items->isEmpty()) {
            throw new InvalidCouponException('Cannot apply a coupon to an empty cart.');
        }

        return $this->evaluate($code, $user, $cart->subtotalCents());
    }

    public function evaluate(string $code, User $user, int $subtotalCents): PromotionEvaluation
    {
        $promotion = $this->promotions->findByCode(trim($code));

        return $this->evaluatePromotion($promotion, $user, $subtotalCents, lockUsages: false);
    }

    public function evaluateLocked(string $code, User $user, int $subtotalCents): PromotionEvaluation
    {
        $promotion = $this->promotions->lockByCode(trim($code));

        return $this->evaluatePromotion($promotion, $user, $subtotalCents, lockUsages: true);
    }

    public function calculateDiscount(Promotion $promotion, int $subtotalCents): int
    {
        $discountCents = match ($promotion->type) {
            PromotionType::Percentage => intdiv(
                $subtotalCents * Money::toCents((string) $promotion->value),
                10000
            ),
            PromotionType::Fixed => Money::toCents((string) $promotion->value),
        };

        if ($promotion->max_discount_amount !== null) {
            $discountCents = min($discountCents, Money::toCents((string) $promotion->max_discount_amount));
        }

        return min($discountCents, $subtotalCents);
    }

    private function evaluatePromotion(
        ?Promotion $promotion,
        User $user,
        int $subtotalCents,
        bool $lockUsages = false,
    ): PromotionEvaluation {
        if (! $promotion || ! $promotion->is_active) {
            throw new InvalidCouponException;
        }

        $this->assertWithinDateWindow($promotion);
        $this->assertUsageLimits($promotion, $user, $lockUsages);
        $this->assertMinimumCartAmount($promotion, $subtotalCents);

        $discountCents = $this->calculateDiscount($promotion, $subtotalCents);
        $totalCents = max(0, $subtotalCents - $discountCents);

        return new PromotionEvaluation(
            promotion: $promotion,
            subtotalCents: $subtotalCents,
            discountCents: $discountCents,
            totalCents: $totalCents,
        );
    }

    private function assertWithinDateWindow(Promotion $promotion): void
    {
        $now = now();

        if ($promotion->starts_at && $now->lt($promotion->starts_at)) {
            throw new InvalidCouponException('Coupon is not active yet.');
        }

        if ($promotion->ends_at && $now->gt($promotion->ends_at)) {
            throw new ExpiredCouponException;
        }
    }

    private function assertUsageLimits(Promotion $promotion, User $user, bool $lockUsages = false): void
    {
        if ($promotion->usage_limit !== null && $promotion->used_count >= $promotion->usage_limit) {
            throw new CouponUsageLimitReachedException;
        }

        if ($promotion->per_customer_usage_limit !== null) {
            $customerUsages = $this->promotions->countUsagesForUser(
                $promotion,
                $user->id,
                $lockUsages,
            );

            if ($customerUsages >= $promotion->per_customer_usage_limit) {
                throw new CouponUsageLimitReachedException('You have reached the usage limit for this coupon.');
            }
        }
    }

    private function assertMinimumCartAmount(Promotion $promotion, int $subtotalCents): void
    {
        $minimumCents = Money::toCents((string) $promotion->min_cart_amount);

        if ($subtotalCents < $minimumCents) {
            throw new CouponMinimumCartException(
                'Cart total must be at least '.$promotion->min_cart_amount.' to use this coupon.'
            );
        }
    }
}
