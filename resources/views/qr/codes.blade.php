<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>QR codes · {{ $area->name }}</title>
        {{-- dompdf understands a limited, older subset of CSS: a table lays
             out the cards, two to a row. --}}
        <style>
            @page { margin: 12mm; }
            body { font-family: DejaVu Sans, sans-serif; color: #1d1714; }
            table { width: 100%; border-collapse: separate; border-spacing: 8mm; }
            td { width: 50%; border: 1.5pt solid {{ $primary }}; border-radius: 6mm; padding: 6mm; text-align: center; vertical-align: top; }
            .business { font-size: 11pt; font-weight: bold; }
            .table { font-size: 26pt; font-weight: bold; color: {{ $primary }}; margin: 2mm 0; }
            .qr { width: 55mm; height: 55mm; }
            .scan { font-size: 13pt; font-weight: bold; margin-top: 2mm; }
            .how { font-size: 8pt; color: #7b6d63; }
        </style>
    </head>
    <body>
        <table>
            @foreach ($cards->chunk(2) as $row)
                <tr>
                    @foreach ($row as $card)
                        <td>
                            <div class="business">{{ $business }}</div>
                            <div class="table">Table {{ $card['name'] }}</div>
                            <img class="qr" src="{{ $card['qr'] }}" alt="QR code">
                            <div class="scan">Scan to order</div>
                            <div class="how">Open your phone's camera and point it at the code. Pay at the counter.</div>
                        </td>
                    @endforeach
                    @if ($row->count() === 1)
                        <td style="border: none;"></td>
                    @endif
                </tr>
            @endforeach
        </table>
        @if ($cards->isEmpty())
            <p>This area has no active tables.</p>
        @endif
    </body>
</html>
