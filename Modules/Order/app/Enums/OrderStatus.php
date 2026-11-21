<?php

namespace Modules\Order\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Placed = 'placed';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Pending, self::Placed, self::Paid], true);
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return false;
        }

        return match ($this) {
            self::Pending, self::Placed => in_array($next, [self::Paid, self::Cancelled], true),
            self::Paid => in_array($next, [self::Shipped, self::Cancelled], true),
            self::Shipped => $next === self::Delivered,
            self::Delivered, self::Cancelled => false,
        };
    }
}
