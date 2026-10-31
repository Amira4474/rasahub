<?php

namespace App\Sales;

use App\Enums\DiscountType;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use App\Support\Money;
use App\Support\Sst;

/**
 * Works out an order's totals from its lines, its discounts and the tax
 * settings it was opened under.
 *
 * Voided lines count for nothing. Line discounts come off their line, then
 * order discounts off what is left, so that the customer is never taxed or
 * charged service on money they did not pay. SST is counted on the whole
 * bill, not line by line, so that rounding each line never adds up to a
 * different amount. A service charge is counted on the amount before SST,
 * and SST is then charged on the service charge too. Last, the total is
 * rounded to 5 sen if the restaurant does so.
 */
class OrderTotals
{
    public static function apply(Order $order): void
    {
        $order->load(['lines.parts', 'discounts']);

        $subtotal = Money::ZERO;
        $afterLineDiscounts = Money::ZERO;
        $discountTotal = Money::ZERO;

        foreach ($order->lines as $line) {
            /** @var OrderItem $line */
            $lineDiscounts = $order->discounts->where('order_item_id', $line->id);

            if ($line->isVoided()) {
                self::settle($lineDiscounts, Money::ZERO);

                if ($line->discount_total !== Money::ZERO) {
                    $line->forceFill(['discount_total' => Money::ZERO])->save();
                }

                continue;
            }

            $gross = $line->grossTotal();
            $lineDiscount = self::settle($lineDiscounts, $gross);

            if ($line->discount_total !== $lineDiscount) {
                $line->forceFill(['discount_total' => $lineDiscount])->save();
            }

            $subtotal = bcadd($subtotal, $gross, 2);
            $afterLineDiscounts = bcadd($afterLineDiscounts, bcsub($gross, $lineDiscount, 2), 2);
            $discountTotal = bcadd($discountTotal, $lineDiscount, 2);
        }

        $orderDiscount = self::settle($order->discounts->whereNull('order_item_id'), $afterLineDiscounts);
        $discountTotal = bcadd($discountTotal, $orderDiscount, 2);

        // What the customer pays for, before SST and service charge.
        $goods = bcsub($afterLineDiscounts, $orderDiscount, 2);
        $rate = $order->sst_rate;

        $goodsSst = Money::ZERO;
        $net = $goods;

        if ($order->sst_enabled) {
            if ($order->prices_include_sst) {
                $goodsSst = Sst::includedIn($goods, $rate);
                $net = bcsub($goods, $goodsSst, 2);
            } else {
                $goodsSst = Sst::addedTo($goods, $rate);
            }
        }

        $serviceCharge = Money::percentOf($net, $order->service_charge_rate);
        $serviceChargeSst = $order->sst_enabled ? Sst::addedTo($serviceCharge, $rate) : Money::ZERO;

        $total = Money::sum([
            $goods,
            $order->prices_include_sst ? Money::ZERO : $goodsSst,
            $serviceCharge,
            $serviceChargeSst,
        ]);

        $rounded = $order->round_to_five_sen ? Money::roundToFiveSen($total) : $total;

        $order->forceFill([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'service_charge' => $serviceCharge,
            'sst' => bcadd($goodsSst, $serviceChargeSst, 2),
            'rounding' => bcsub($rounded, $total, 2),
            'total' => $rounded,
        ])->save();
    }

    /**
     * Work out each discount against what is left of the base, in the order
     * they were given, so that together they never come to more than it.
     *
     * @param  iterable<OrderDiscount>  $discounts
     * @return string The total taken off.
     */
    private static function settle(iterable $discounts, string $base): string
    {
        $remaining = $base;
        $taken = Money::ZERO;

        foreach ($discounts as $discount) {
            $amount = $discount->type === DiscountType::Percent
                ? Money::percentOf($base, $discount->value)
                : $discount->value;

            $amount = Money::min($amount, $remaining);
            $remaining = bcsub($remaining, $amount, 2);
            $taken = bcadd($taken, $amount, 2);

            if ($discount->amount !== $amount) {
                $discount->forceFill(['amount' => $amount])->save();
            }
        }

        return $taken;
    }
}
