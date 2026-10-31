<?php

namespace App\Sales;

use App\Models\Order;
use App\Support\Money;
use App\Support\Sst;

/**
 * Works out an order's totals from its lines and the tax settings it was
 * opened under.
 *
 * SST is counted on the whole bill, not line by line, so that rounding each
 * line never adds up to a different amount. A service charge is counted on
 * the amount before SST, and SST is then charged on the service charge too.
 * Last, the total is rounded to 5 sen if the restaurant does so.
 */
class OrderTotals
{
    public static function apply(Order $order): void
    {
        $subtotal = Money::sum($order->items()->pluck('line_total'));
        $rate = $order->sst_rate;

        $itemsSst = Money::ZERO;
        $net = $subtotal;

        if ($order->sst_enabled) {
            if ($order->prices_include_sst) {
                $itemsSst = Sst::includedIn($subtotal, $rate);
                $net = bcsub($subtotal, $itemsSst, 2);
            } else {
                $itemsSst = Sst::addedTo($subtotal, $rate);
            }
        }

        $serviceCharge = Money::percentOf($net, $order->service_charge_rate);
        $serviceChargeSst = $order->sst_enabled ? Sst::addedTo($serviceCharge, $rate) : Money::ZERO;

        $total = Money::sum([
            $subtotal,
            $order->prices_include_sst ? Money::ZERO : $itemsSst,
            $serviceCharge,
            $serviceChargeSst,
        ]);

        $rounded = $order->round_to_five_sen ? Money::roundToFiveSen($total) : $total;

        $order->forceFill([
            'subtotal' => $subtotal,
            'service_charge' => $serviceCharge,
            'sst' => bcadd($itemsSst, $serviceChargeSst, 2),
            'rounding' => bcsub($rounded, $total, 2),
            'total' => $rounded,
        ])->save();
    }
}
