<?php

namespace App\Support;

final class Money
{
    /**
     * Convert a decimal money value to integer cents without relying on float arithmetic for the final result.
     */
    public static function toCents(string|int|float $amount): int
    {
        $normalized = is_string($amount)
            ? $amount
            : number_format((float) $amount, 2, '.', '');

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw new \InvalidArgumentException("Invalid money amount [{$normalized}].");
        }

        $sign = $matches[1] === '-' ? -1 : 1;
        $dollars = (int) $matches[2];
        $fraction = str_pad($matches[3] ?? '0', 2, '0');

        return $sign * (($dollars * 100) + (int) $fraction);
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        return ($negative ? '-' : '').sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
