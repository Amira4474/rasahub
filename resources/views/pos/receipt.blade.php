@php($money = fn (?string $amount): string => number_format((float) $amount, 2))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>Receipt {{ $order->receiptLabel() ?? $order->number_label }}</title>
        {{-- Laid out for an 80 mm thermal printer; 58 mm printers scale it down. --}}
        <style>
            @page { size: 80mm auto; margin: 0; }
            * { box-sizing: border-box; }
            body { margin: 0; background: #fff; color: #000; font: 12px/1.35 ui-monospace, "Courier New", monospace; }
            .receipt { width: 72mm; margin: 0 auto; padding: 4mm 0; }
            .center { text-align: center; }
            .row { display: flex; justify-content: space-between; gap: 2mm; }
            .muted { font-size: 11px; }
            .total { font-size: 15px; font-weight: 700; }
            hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
            img { max-width: 40mm; max-height: 20mm; }
            p { margin: 0; }
            .actions { margin: 4mm auto; text-align: center; }
            @media print { .actions { display: none; } }
        </style>
    </head>
    <body onload="window.print()">
        <div class="receipt">
            <div class="center">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $business->name }}">
                @endif
                <p style="font-weight: 700; font-size: 14px;">{{ $business->name }}</p>
                @if ($settings->receipt_header)
                    <p class="muted">{!! nl2br(e($settings->receipt_header)) !!}</p>
                @endif
                <p class="muted">{{ $order->branch->name }}</p>
                @if ($order->branch->address)
                    <p class="muted">{!! nl2br(e($order->branch->address)) !!}</p>
                @endif
                @if ($order->branch->phone)
                    <p class="muted">Tel: {{ $order->branch->phone }}</p>
                @endif
                @if ($order->sst_enabled && $settings->sst_registration_number)
                    <p class="muted">SST No: {{ $settings->sst_registration_number }}</p>
                @endif
            </div>

            <hr>
            <div class="row"><span>Receipt</span><span>{{ $order->receiptLabel() ?? 'Not paid yet' }}</span></div>
            <div class="row"><span>Order</span><span>{{ $order->number_label }}</span></div>
            <div class="row"><span>{{ $order->placeLabel() }}</span><span>{{ ($order->completed_at ?? $order->created_at)->timezone($timezone)->format('d/m/Y H:i') }}</span></div>
            @if ($order->customer)
                <div class="row"><span>Customer</span><span>{{ $order->customer->displayName() }}</span></div>
            @endif
            <hr>

            @foreach ($order->lines->reject->isVoided() as $line)
                <div class="row"><span>{{ $line->quantity }} {{ $line->name }}</span><span>{{ $money($line->grossTotal()) }}</span></div>
                @foreach ($line->modifiers as $modifier)
                    <p class="muted">&nbsp;&nbsp;{{ $modifier->name }}</p>
                @endforeach
                @foreach ($line->parts as $part)
                    <p class="muted">&nbsp;&nbsp;- {{ $part->name }}</p>
                @endforeach
                @foreach ($line->discounts as $discount)
                    <div class="row muted"><span>&nbsp;&nbsp;{{ $discount->label() }}</span><span>-{{ $money($discount->amount) }}</span></div>
                @endforeach
            @endforeach

            <hr>
            <div class="row"><span>Subtotal</span><span>{{ $money($order->subtotal) }}</span></div>
            @foreach ($order->discounts->whereNull('order_item_id') as $discount)
                <div class="row"><span>{{ $discount->label() }}</span><span>-{{ $money($discount->amount) }}</span></div>
            @endforeach
            @if ($order->service_charge !== '0.00')
                <div class="row"><span>Service charge {{ rtrim(rtrim($order->service_charge_rate, '0'), '.') }}%</span><span>{{ $money($order->service_charge) }}</span></div>
            @endif
            @if ($order->sst_enabled)
                <div class="row"><span>SST {{ rtrim(rtrim($order->sst_rate, '0'), '.') }}%{{ $order->prices_include_sst ? ' (included)' : '' }}</span><span>{{ $money($order->sst) }}</span></div>
            @endif
            @if ($order->rounding !== '0.00')
                <div class="row"><span>Rounding</span><span>{{ $order->rounding }}</span></div>
            @endif
            <div class="row total"><span>TOTAL {{ $business->currency }}</span><span>{{ $money($order->total) }}</span></div>

            @if ($order->payments->isNotEmpty())
                <hr>
                @foreach ($order->payments as $payment)
                    <div class="row"><span>{{ $payment->method_name }}{{ $payment->reference ? ' '.$payment->reference : '' }}</span><span>{{ $money($payment->tendered ?? $payment->amount) }}</span></div>
                    @if ($payment->change !== null && $payment->change !== '0.00')
                        <div class="row"><span>Change</span><span>{{ $money($payment->change) }}</span></div>
                    @endif
                @endforeach
            @endif

            @if ($order->refunds->isNotEmpty())
                <hr>
                @foreach ($order->refunds as $refund)
                    <div class="row"><span>Refund {{ $refund->number_label }}</span><span>-{{ $money($refund->amount) }}</span></div>
                @endforeach
            @endif

            @if ($settings->receipt_footer)
                <hr>
                <p class="center">{!! nl2br(e($settings->receipt_footer)) !!}</p>
            @endif
        </div>

        <div class="actions">
            <button type="button" onclick="window.print()">Print again</button>
            <button type="button" onclick="window.close()">Close</button>
        </div>
    </body>
</html>
