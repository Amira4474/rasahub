<?php

namespace App\Support;

/**
 * Sales and Service Tax arithmetic, on money held as decimal strings so
 * that no floating-point rounding ever touches an amount.
 */
class Sst
{
    /**
     * The SST contained in a price that already includes it: at 6%, RM 10.60
     * contains RM 0.60 (10.60 × 6 ÷ 106).
     */
    public static function includedIn(string $price, string $rate): string
    {
        return Money::round(bcdiv(bcmul($price, $rate, 6), bcadd('100', $rate, 6), 6));
    }

    /**
     * The SST to add on top of a price that does not include it.
     */
    public static function addedTo(string $price, string $rate): string
    {
        return Money::percentOf($price, $rate);
    }

    /**
     * A worked example for the settings page.
     */
    public static function example(bool $included, string $rate, string $currency): string
    {
        $rate = number_format((float) $rate, 2, '.', '');
        $label = rtrim(rtrim($rate, '0'), '.');

        if ($included) {
            $price = bcadd('10.00', self::addedTo('10.00', $rate), 2);

            return "Example: a menu price of {$currency} {$price} includes {$currency} ".self::includedIn($price, $rate)." SST. Receipts show \"SST {$label}% (included)\".";
        }

        return "Example: a menu price of {$currency} 10.00 has {$currency} ".self::addedTo('10.00', $rate)." SST added at the till, making {$currency} ".bcadd('10.00', self::addedTo('10.00', $rate), 2).'.';
    }
}
