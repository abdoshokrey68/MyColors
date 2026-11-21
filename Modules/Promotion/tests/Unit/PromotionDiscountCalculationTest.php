<?php

namespace Modules\Promotion\Tests\Unit;

use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Promotion\Enums\PromotionType;
use Modules\Promotion\Models\Promotion;
use Modules\Promotion\Services\PromotionService;
use Tests\TestCase;

class PromotionDiscountCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_discount_covers_percentage_fixed_cap_and_rounding(): void
    {
        $service = app(PromotionService::class);

        $percentage = Promotion::factory()->make([
            'type' => PromotionType::Percentage,
            'value' => 20,
            'max_discount_amount' => null,
        ]);
        $this->assertSame(2400, $service->calculateDiscount($percentage, 12000));

        $capped = Promotion::factory()->make([
            'type' => PromotionType::Percentage,
            'value' => 50,
            'max_discount_amount' => 10,
        ]);
        $this->assertSame(1000, $service->calculateDiscount($capped, 5000));

        $fixed = Promotion::factory()->make([
            'type' => PromotionType::Fixed,
            'value' => 15,
            'max_discount_amount' => null,
        ]);
        $this->assertSame(1500, $service->calculateDiscount($fixed, 5000));

        $overSubtotal = Promotion::factory()->make([
            'type' => PromotionType::Fixed,
            'value' => 50,
            'max_discount_amount' => null,
        ]);
        $this->assertSame(1000, $service->calculateDiscount($overSubtotal, 1000));

        $rounding = Promotion::factory()->make([
            'type' => PromotionType::Percentage,
            'value' => 33.33,
            'max_discount_amount' => null,
        ]);
        $this->assertSame(333, $service->calculateDiscount($rounding, 1000));
        $this->assertSame('3.33', Money::fromCents(333));
    }
}
