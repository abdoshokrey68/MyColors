<?php

namespace Modules\Promotion\Services;

use App\Models\User;
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

        return $this->evaluate($code, $user, $cart->subtotal());
    }

    public function evaluate(string $code, User $user, float $subtotal): PromotionEvaluation
    {
        $promotion = $this->promotions->findByCode(trim($code));

        if (! $promotion || ! $promotion->is_active) {
            throw new InvalidCouponException;
        }

        $this->assertWithinDateWindow($promotion);
        $this->assertUsageLimits($promotion, $user);
        $this->assertMinimumCartAmount($promotion, $subtotal);

        $discount = $this->calculateDiscount($promotion, $subtotal);
        $total = max(0, round($subtotal - $discount, 2));

        return new PromotionEvaluation(
            promotion: $promotion,
            subtotal: round($subtotal, 2),
            discountAmount: $discount,
            total: $total,
        );
    }

    public function calculateDiscount(Promotion $promotion, float $subtotal): float
    {
        $discount = match ($promotion->type) {
            PromotionType::Percentage => $subtotal * ((float) $promotion->value / 100),
            PromotionType::Fixed => (float) $promotion->value,
        };

        if ($promotion->max_discount_amount !== null) {
            $discount = min($discount, (float) $promotion->max_discount_amount);
        }

        return round(min($discount, $subtotal), 2);
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

    private function assertUsageLimits(Promotion $promotion, User $user): void
    {
        if ($promotion->usage_limit !== null && $promotion->used_count >= $promotion->usage_limit) {
            throw new CouponUsageLimitReachedException;
        }

        if ($promotion->per_customer_usage_limit !== null) {
            $customerUsages = $this->promotions->countUsagesForUser($promotion, $user->id);

            if ($customerUsages >= $promotion->per_customer_usage_limit) {
                throw new CouponUsageLimitReachedException('You have reached the usage limit for this coupon.');
            }
        }
    }

    private function assertMinimumCartAmount(Promotion $promotion, float $subtotal): void
    {
        if ($subtotal < (float) $promotion->min_cart_amount) {
            throw new CouponMinimumCartException(
                'Cart total must be at least '.$promotion->min_cart_amount.' to use this coupon.'
            );
        }
    }
}
