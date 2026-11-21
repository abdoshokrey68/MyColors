<?php

namespace Modules\Promotion\DTOs;

use App\Support\Money;
use Modules\Promotion\Models\Promotion;

readonly class PromotionEvaluation
{
    public function __construct(
        public Promotion $promotion,
        public int $subtotalCents,
        public int $discountCents,
        public int $totalCents,
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
            'subtotal' => Money::fromCents($this->subtotalCents),
            'discount_amount' => Money::fromCents($this->discountCents),
            'total' => Money::fromCents($this->totalCents),
        ];
    }
}
