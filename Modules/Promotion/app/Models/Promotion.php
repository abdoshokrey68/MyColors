<?php

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Promotion\Database\Factories\PromotionFactory;
use Modules\Promotion\Enums\PromotionType;

class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_cart_amount',
        'max_discount_amount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'per_customer_usage_limit',
        'used_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'decimal:2',
            'min_cart_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'per_customer_usage_limit' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): PromotionFactory
    {
        return PromotionFactory::new();
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }
}
