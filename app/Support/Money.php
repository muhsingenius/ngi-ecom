<?php

namespace App\Support;

/**
 * Money is held as integer minor units (pesewas) while calculating, so totals never drift.
 */
class Money
{
    public static function toMinor(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function toDecimal(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    public static function symbol(?string $currency = null): string
    {
        return match ($currency ?? config('shop.currency')) {
            'GHS' => 'GH₵',
            'USD' => '$',
            default => ($currency ?? config('shop.currency')).' ',
        };
    }

    public static function format(int $minor, ?string $currency = null): string
    {
        $value = $minor / 100;
        $decimals = $minor % 100 === 0 ? 0 : 2;

        return self::symbol($currency).' '.number_format($value, $decimals);
    }
}
