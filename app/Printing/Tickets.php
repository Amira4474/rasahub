<?php

namespace App\Printing;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\Refund;
use App\Models\RestaurantSetting;
use App\Models\Shift;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;

/**
 * The layouts of everything RasaHub prints on a thermal printer.
 */
class Tickets
{
    /**
     * A ticket for one station: what to make, for whom, in large type.
     *
     * @param  Collection<int, OrderItem>  $lines
     */
    public static function kitchen(Order $order, string $station, Collection $lines): Document
    {
        $document = (new Document)
            ->center(mb_strtoupper($station), bold: true)
            ->center($order->number_label, large: true, bold: true)
            ->center($order->placeLabel().($order->guests ? " · {$order->guests} guests" : ''), large: true)
            ->center(now()->timezone($order->branch->effectiveTimezone())->format('d/m H:i'))
            ->rule();

        foreach ($lines as $line) {
            $document->text("{$line->quantity} x {$line->name}", large: true, bold: true);

            foreach ($line->modifiers as $modifier) {
                $document->text("   {$modifier->name}");
            }

            if ($line->note) {
                $document->text("   ** {$line->note} **", bold: true);
            }
        }

        return $document->rule()->feed(2)->cut();
    }

    /**
     * Tells a station to stop making a line that was voided.
     */
    public static function void(Order $order, string $station, OrderItem $line): Document
    {
        return (new Document)
            ->center('*** VOID ***', large: true, bold: true)
            ->center(mb_strtoupper($station), bold: true)
            ->center($order->number_label.' · '.$order->placeLabel(), large: true)
            ->rule()
            ->text("{$line->quantity} x {$line->name}", large: true, bold: true)
            ->text('Do not make. '.$line->void_reason)
            ->rule()
            ->feed(2)
            ->cut();
    }

    public static function receipt(Order $order): Document
    {
        $order->loadMissing(['lines.parts', 'lines.modifiers', 'lines.discounts', 'discounts', 'payments', 'refunds', 'branch', 'customer']);
        $settings = RestaurantSetting::current();
        $business = app(TenantContext::class)->business();
        $money = fn (?string $amount): string => number_format((float) $amount, 2);
        $rate = rtrim(rtrim($order->sst_rate, '0'), '.');

        $document = (new Document)->center($business->name, large: true, bold: true);

        foreach (array_filter(explode("\n", (string) $settings->receipt_header)) as $line) {
            $document->center(trim($line));
        }

        $document->center($order->branch->name);

        foreach (array_filter(explode("\n", (string) $order->branch->address)) as $line) {
            $document->center(trim($line));
        }

        if ($order->branch->phone) {
            $document->center('Tel: '.$order->branch->phone);
        }

        if ($order->sst_enabled && $settings->sst_registration_number) {
            $document->center('SST No: '.$settings->sst_registration_number);
        }

        $document->rule()
            ->row('Receipt', (string) $order->receiptLabel())
            ->row('Order', $order->number_label)
            ->row($order->placeLabel(), ($order->completed_at ?? $order->created_at)->timezone($order->branch->effectiveTimezone())->format('d/m/Y H:i'));

        if ($order->customer) {
            $document->row('Customer', $order->customer->displayName());
        }

        $document->rule();

        foreach ($order->lines->reject->isVoided() as $line) {
            $document->row("{$line->quantity} {$line->name}", $money($line->grossTotal()));

            foreach ($line->modifiers as $modifier) {
                $document->text("  {$modifier->name}");
            }

            foreach ($line->parts as $part) {
                $document->text("  - {$part->name}");
            }

            foreach ($line->discounts as $discount) {
                $document->row("  {$discount->label()}", '-'.$money($discount->amount));
            }
        }

        $document->rule()->row('Subtotal', $money($order->subtotal));

        foreach ($order->discounts->whereNull('order_item_id') as $discount) {
            $document->row($discount->label(), '-'.$money($discount->amount));
        }

        if ($order->service_charge !== '0.00') {
            $document->row('Service charge '.rtrim(rtrim($order->service_charge_rate, '0'), '.').'%', $money($order->service_charge));
        }

        if ($order->sst_enabled) {
            $document->row("SST {$rate}%".($order->prices_include_sst ? ' (included)' : ''), $money($order->sst));
        }

        if ($order->rounding !== '0.00') {
            $document->row('Rounding', $order->rounding);
        }

        $document->row('TOTAL '.$business->currency, $money($order->total), bold: true);

        if ($order->payments->isNotEmpty()) {
            $document->rule();

            foreach ($order->payments as $payment) {
                $document->row($payment->method_name.($payment->reference ? ' '.$payment->reference : ''), $money($payment->tendered ?? $payment->amount));

                if ($payment->change !== null && $payment->change !== '0.00') {
                    $document->row('Change', $money($payment->change));
                }
            }
        }

        foreach ($order->refunds as $refund) {
            $document->row('Refund '.$refund->number_label, '-'.$money($refund->amount));
        }

        if ($settings->receipt_footer) {
            $document->rule();

            foreach (array_filter(explode("\n", $settings->receipt_footer)) as $line) {
                $document->center(trim($line));
            }
        }

        return $document->feed(2)->cut();
    }

    public static function refund(Refund $refund): Document
    {
        $business = app(TenantContext::class)->business();
        $money = fn (?string $amount): string => number_format((float) $amount, 2);

        $document = (new Document)
            ->center($business->name, bold: true)
            ->center($refund->branch->name)
            ->center('REFUND', large: true, bold: true)
            ->rule()
            ->row('Refund', $refund->number_label)
            ->row('Order', $refund->order->number_label)
            ->row('Receipt', (string) $refund->order->receiptLabel())
            ->row('Date', $refund->created_at->timezone($refund->branch->effectiveTimezone())->format('d/m/Y H:i'))
            ->rule();

        foreach ($refund->items ?? [] as $item) {
            $document->text("{$item['quantity']} {$item['name']}");
        }

        return $document->text('Reason: '.$refund->reason)
            ->rule()
            ->row('REFUND '.$business->currency, $money($refund->amount), bold: true)
            ->row('Paid in', $refund->method_name)
            ->rule()
            ->text('Given by: '.(User::acrossBusinesses()->find($refund->refunded_by)?->name ?? ''))
            ->text($refund->approved_by ? 'Approved by: '.(User::acrossBusinesses()->find($refund->approved_by)?->name ?? '') : '')
            ->feed(3)
            ->text('Customer signature: ______________')
            ->feed(2)
            ->cut();
    }

    /**
     * The end-of-shift report: what should be in the drawer and what was
     * counted, and the takings by method.
     */
    public static function shift(Shift $shift): Document
    {
        $money = fn (?string $amount): string => number_format((float) $amount, 2);
        $timezone = $shift->branch->effectiveTimezone();
        $name = fn (?int $id): string => $id === null ? '' : (User::acrossBusinesses()->find($id)?->name ?? 'Removed user');

        $document = (new Document)
            ->center('SHIFT REPORT', large: true, bold: true)
            ->center($shift->branch->name.' · '.$shift->terminal->name)
            ->rule()
            ->row('Opened', $shift->opened_at->timezone($timezone)->format('d/m H:i').' '.$name($shift->opened_by))
            ->row('Closed', $shift->closed_at?->timezone($timezone)->format('d/m H:i').' '.$name($shift->closed_by))
            ->rule();

        foreach ($shift->payments()->get()->groupBy('method_name') as $method => $payments) {
            $document->row($method.' ('.$payments->count().')', $money((string) $payments->sum('amount')));
        }

        $refunds = $shift->refunds()->get();

        if ($refunds->isNotEmpty()) {
            $document->row('Cash refunds ('.$refunds->count().')', '-'.$money((string) $refunds->sum('amount')));
        }

        return $document->rule()
            ->row('Opening float', $money($shift->opening_float))
            ->row('Cash expected', $money($shift->expected_cash), bold: true)
            ->row('Cash counted', $money($shift->counted_cash), bold: true)
            ->row('Difference', $money($shift->variance), bold: true)
            ->text($shift->closing_note ? 'Note: '.$shift->closing_note : '')
            ->feed(2)
            ->cut();
    }

    public static function test(Printer $printer): Document
    {
        return (new Document)
            ->center('RasaHub', large: true, bold: true)
            ->center('Test print')
            ->rule()
            ->row('Printer', $printer->name)
            ->row('Address', "{$printer->host}:{$printer->port}")
            ->row('Paper', "{$printer->paper_width} mm")
            ->row('Sent', now()->timezone($printer->branch->effectiveTimezone())->format('d/m/Y H:i:s'))
            ->rule()
            ->center('If you can read this, the printer works.')
            ->feed(2)
            ->cut();
    }
}
