{{-- A print document (see App\Printing\Document) laid out as a receipt
     printer would print it, for printing from the browser. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $title }}</title>
        <style>
            @page { size: 80mm auto; margin: 0; }
            body { margin: 0; background: #fff; color: #000; font: 12px/1.35 ui-monospace, "Courier New", monospace; }
            .paper { width: 72mm; margin: 0 auto; padding: 4mm 0; }
            .line { white-space: pre-wrap; }
            .center { text-align: center; }
            .right { text-align: right; }
            .large { font-size: 16px; }
            .bold { font-weight: 700; }
            .row { display: flex; justify-content: space-between; gap: 2mm; }
            hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
            .actions { margin: 4mm auto; text-align: center; }
            @media print { .actions { display: none; } }
        </style>
    </head>
    <body onload="window.print()">
        <div class="paper">
            @foreach ($document['lines'] as $line)
                @switch($line['type'])
                    @case('text')
                        <div class="line {{ $line['align'] ?? 'left' }} {{ ($line['size'] ?? '') === 'large' ? 'large' : '' }} {{ ! empty($line['bold']) ? 'bold' : '' }}">{{ $line['text'] }}&nbsp;</div>
                        @break
                    @case('row')
                        <div class="row {{ ! empty($line['bold']) ? 'bold' : '' }}"><span>{{ $line['left'] }}</span><span>{{ $line['right'] }}</span></div>
                        @break
                    @case('rule')
                        <hr>
                        @break
                    @case('feed')
                        @for ($i = 0; $i < ($line['lines'] ?? 1); $i++)<div>&nbsp;</div>@endfor
                        @break
                @endswitch
            @endforeach
        </div>
        <div class="actions">
            <button type="button" onclick="window.print()">Print again</button>
            <button type="button" onclick="window.close()">Close</button>
        </div>
    </body>
</html>
