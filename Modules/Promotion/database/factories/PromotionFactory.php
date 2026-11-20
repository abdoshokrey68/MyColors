<?php

namespace Modules\Promotion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Promotion\Enums\PromotionType;
use Modules\Promotion\Models\Promotion;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'type' => PromotionType::Percentage,
            'value' => 10,
            'min_cart_amount' => 0,
            'max_discount_amount' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'usage_limit' => null,
            'per_customer_usage_limit' => null,
            'used_count' => 0,
            'is_active' => true,
        ];
    }

    public function fixed(float $amount = 20): static
    {
        return $this->state(fn (): array => [
            'type' => PromotionType::Fixed,
            'value' => $amount,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }
}
