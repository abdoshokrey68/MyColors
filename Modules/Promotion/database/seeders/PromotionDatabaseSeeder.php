<?php

namespace Modules\Promotion\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Promotion\Enums\PromotionType;
use Modules\Promotion\Models\Promotion;

class PromotionDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Promotion::query()->updateOrCreate(
            ['code' => 'SUMMER20'],
            [
                'type' => PromotionType::Percentage,
                'value' => 20,
                'min_cart_amount' => 100,
                'max_discount_amount' => 50,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(3),
                'usage_limit' => 1000,
                'per_customer_usage_limit' => 1,
                'used_count' => 0,
                'is_active' => true,
            ]
        );

        Promotion::query()->updateOrCreate(
            ['code' => 'FLAT15'],
            [
                'type' => PromotionType::Fixed,
                'value' => 15,
                'min_cart_amount' => 50,
                'max_discount_amount' => null,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(3),
                'usage_limit' => null,
                'per_customer_usage_limit' => 3,
                'used_count' => 0,
                'is_active' => true,
            ]
        );
    }
}
