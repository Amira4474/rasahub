@php
    $format = function (mixed $value, string $type) use ($currency): string {
        if ($value === '' || $value === null) {
            return '';
        }

        return match ($type) {
            'money' => is_numeric($value) ? number_format((float) $value, 2) : (string) $value,
            'int' => is_numeric($value) ? number_format((int) $value) : (string) $value,
            'percent' => is_numeric($value) ? number_format((float) $value, 1).'%' : (string) $value,
            default => (string) $value,
        };
    };
    $right = fn (string $type): bool => in_array($type, ['money', 'int', 'percent'], true);
@endphp
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>{{ $table->title }}</title>
        {{-- dompdf understands a limited, older subset of CSS. --}}
        <style>
            @page { margin: 18mm 14mm 16mm; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1d1714; }
            h1 { font-size: 16pt; margin: 0; }
            .meta { color: #7b6d63; margin: 2pt 0 10pt; }
            .highlights { width: 100%; margin-bottom: 10pt; }
            .highlights td { padding: 6pt 8pt; background: #fbf3e7; }
            .highlights .label { font-size: 8pt; color: #7b6d63; }
            .highlights .value { font-size: 12pt; font-weight: bold; }
            table.report { width: 100%; border-collapse: collapse; }
            table.report th { background: #1d1714; color: #fff; text-align: left; padding: 4pt 5pt; font-size: 8pt; }
            table.report td { padding: 3pt 5pt; border-bottom: 0.5pt solid #e3d2b9; }
            table.report tr.total td { font-weight: bold; border-top: 1pt solid #1d1714; border-bottom: none; }
            .right { text-align: right; }
            .note { margin-top: 10pt; color: #7b6d63; font-size: 8pt; }
            .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #7b6d63; }
        </style>
    </head>
    <body>
        <div class="footer">{{ $business }} · {{ $subtitle }} · Printed {{ now()->format('j M Y H:i') }} · RasaHub {{ config('app.version') }}</div>

        <h1>{{ $table->title }}</h1>
        <p class="meta">{{ $business }} · {{ $subtitle }} · Amounts in {{ $currency }}</p>

        @if ($table->highlights !== [])
            <table class="highlights">
                <tr>
                    @foreach ($table->highlights as $highlight)
                        <td>
                            <div class="label">{{ $highlight['label'] }}</div>
                            <div class="value">{{ $format($highlight['value'], $highlight['type'] ?? 'text') }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif

        <table class="report">
            <thead>
                <tr>
                    @foreach ($table->columns as $column)
                        <th class="{{ $right($column['type']) ? 'right' : '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($table->rows as $row)
                    <tr>
                        @foreach ($table->columns as $column)
                            <td class="{{ $right($column['type']) ? 'right' : '' }}">{{ $format($row[$column['key']] ?? '', $column['type']) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($table->columns) }}">Nothing in this period.</td></tr>
                @endforelse
                @if ($table->totals !== null && $table->rows !== [])
                    <tr class="total">
                        @foreach ($table->columns as $column)
                            <td class="{{ $right($column['type']) ? 'right' : '' }}">{{ $format($table->totals[$column['key']] ?? '', $column['type']) }}</td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>

        @if ($table->note)
            <p class="note">{{ $table->note }}</p>
        @endif
    </body>
</html>
