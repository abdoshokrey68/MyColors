<?php

namespace Modules\Promotion\DTOs;

use Modules\Promotion\Models\Promotion;

readonly class PromotionEvaluation
{
    public function __construct(
        public Promotion $promotion,
        public float $subtotal,
        public float $discountAmount,
        public float $total,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->promotion->code,
            'type' => $this->promotion->type->value,
            'value' => $this->promotion->value,
            'subtotal' => number_format($this->subtotal, 2, '.', ''),
            'discount_amount' => number_format($this->discountAmount, 2, '.', ''),
            'total' => number_format($this->total, 2, '.', ''),
        ];
    }
}
