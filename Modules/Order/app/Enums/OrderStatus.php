<?php

namespace Modules\Order\Enums;

enum OrderStatus: string
{
    case Placed = 'placed';
    case Cancelled = 'cancelled';

    public function canBeCancelled(): bool
    {
        return $this === self::Placed;
    }
}
