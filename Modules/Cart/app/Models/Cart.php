<?php

namespace Modules\Cart\Models;

use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function subtotalCents(): int
    {
        return (int) $this->items->sum(function (CartItem $item): int {
            return Money::toCents((string) $item->product->price) * $item->quantity;
        });
    }

    public function subtotal(): float
    {
        return (float) Money::fromCents($this->subtotalCents());
    }
}
