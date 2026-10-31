<?php

namespace App\Support;

/**
 * Arithmetic on amounts of money held as decimal strings with two places,
 * so that no floating-point rounding ever touches them.
 */
class Money
{
    public const ZERO = '0.00';

    /**
     * A percentage of an amount, rounded half up to the sen.
     */
    public static function percentOf(string $amount, string $rate): string
    {
        return self::round(bcdiv(bcmul($amount, $rate, 6), '100', 6));
    }

    /**
     * Round half up to the sen.
     */
    public static function round(string $amount): string
    {
        return bccomp($amount, '0', 6) < 0
            ? bcsub($amount, '0.005', 2)
            : bcadd($amount, '0.005', 2);
    }

    /**
     * To the nearest 5 sen, as Malaysian totals are: 1 or 2 sen goes down,
     * 3 or 4 goes up to 5, 6 or 7 goes down to 5, and 8 or 9 goes up.
     */
    public static function roundToFiveSen(string $amount): string
    {
        $sen = (int) bcmul($amount, '100', 0);
        $remainder = $sen % 5;
        $sen += $remainder <= 2 ? -$remainder : 5 - $remainder;

        return bcdiv((string) $sen, '100', 2);
    }

    /**
     * @param  iterable<string>  $amounts
     */
    public static function sum(iterable $amounts): string
    {
        $total = self::ZERO;

        foreach ($amounts as $amount) {
            $total = bcadd($total, (string) $amount, 2);
        }

        return $total;
    }

    public static function min(string $a, string $b): string
    {
        return bccomp($a, $b, 2) <= 0 ? $a : $b;
    }

    public static function isPositive(string $amount): bool
    {
        return bccomp($amount, '0', 2) > 0;
    }

    /**
     * An amount typed by a person, as a decimal string, or null if it is not
     * one.
     */
    public static function parse(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{1,9}(\.\d{1,2})?$/', $value) === 1 ? bcadd($value, '0', 2) : null;
    }
}
