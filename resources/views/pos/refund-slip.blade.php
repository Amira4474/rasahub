@php($money = fn (?string $amount): string => number_format((float) $amount, 2))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>Refund {{ $refund->number_label }}</title>
        <style>
            @page { size: 80mm auto; margin: 0; }
            * { box-sizing: border-box; }
            body { margin: 0; background: #fff; color: #000; font: 12px/1.35 ui-monospace, "Courier New", monospace; }
            .slip { width: 72mm; margin: 0 auto; padding: 4mm 0; }
            .center { text-align: center; }
            .row { display: flex; justify-content: space-between; gap: 2mm; }
            .total { font-size: 15px; font-weight: 700; }
            hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
            p { margin: 0; }
            .sign { margin-top: 10mm; border-top: 1px solid #000; padding-top: 1mm; }
            .actions { margin: 4mm auto; text-align: center; }
            @media print { .actions { display: none; } }
        </style>
    </head>
    <body onload="window.print()">
        <div class="slip">
            <div class="center">
                <p style="font-weight: 700; font-size: 14px;">{{ $business->name }}</p>
                <p>{{ $refund->branch->name }}</p>
                <p style="font-weight: 700; margin-top: 2mm;">REFUND</p>
            </div>
            <hr>
            <div class="row"><span>Refund</span><span>{{ $refund->number_label }}</span></div>
            <div class="row"><span>Order</span><span>{{ $refund->order->number_label }}</span></div>
            <div class="row"><span>Receipt</span><span>{{ $refund->order->receiptLabel() }}</span></div>
            <div class="row"><span>Date</span><span>{{ $refund->created_at->timezone($timezone)->format('d/m/Y H:i') }}</span></div>
            <hr>
            @foreach ($refund->items ?? [] as $item)
                <div class="row"><span>{{ $item['quantity'] }} {{ $item['name'] }}</span></div>
            @endforeach
            <p>Reason: {{ $refund->reason }}</p>
            <hr>
            <div class="row total"><span>REFUND {{ $business->currency }}</span><span>{{ $money($refund->amount) }}</span></div>
            <div class="row"><span>Paid in</span><span>{{ $refund->method_name }}</span></div>
            <hr>
            <p>Given by: {{ $refundedBy }}</p>
            @if ($approvedBy)
                <p>Approved by: {{ $approvedBy }}</p>
            @endif
            <p class="sign">Customer signature</p>
        </div>

        <div class="actions">
            <button type="button" onclick="window.print()">Print again</button>
            <button type="button" onclick="window.close()">Close</button>
        </div>
    </body>
</html>
