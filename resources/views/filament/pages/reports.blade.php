@php
    $table = $this->table;
    $currency = $this->currency();
    $format = function (mixed $value, string $type): string {
        if ($value === '' || $value === null) {
            return '';
        }

        return match ($type) {
            'money' => is_numeric($value) ? number_format((float) $value, 2) : (string) $value,
            'int' => is_numeric($value) ? number_format((int) $value) : (string) $value,
            'percent' => is_numeric($value) ? number_format((float) $value, 1).'%' : (string) $value,
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? \Carbon\Carbon::parse($value)->format('D j M') : (string) $value,
            default => (string) $value,
        };
    };
    $numeric = fn (string $type): bool => in_array($type, ['money', 'int', 'percent'], true);
    // Filament does not load the website's styles, so this page is styled inline.
    $cell = 'padding: 0.5rem 0.75rem; border-bottom: 1px solid rgb(107 114 128 / 0.15);';
@endphp

<x-filament-panels::page>
    {{-- The report is chosen from a list above it, so the report itself has
         the whole width of the page. --}}
    <div style="display: grid; gap: 1rem; min-width: 0;">
            {{-- Filters and downloads --}}
            <x-filament::section>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; justify-content: space-between;">
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end;">
                        <label style="display: grid; gap: 0.25rem; font-size: 0.8rem;">Report
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="report" style="min-width: 14rem;">
                                    @foreach ($this->available() as $key => $item)
                                        <option value="{{ $key }}">{{ $item['title'] }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </label>
                        <label style="display: grid; gap: 0.25rem; font-size: 0.8rem;">From
                            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
                        </label>
                        <label style="display: grid; gap: 0.25rem; font-size: 0.8rem;">To
                            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="to" /></x-filament::input.wrapper>
                        </label>
                        @if ($this->branches->count() > 1)
                            <label style="display: grid; gap: 0.25rem; font-size: 0.8rem;">Branch
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:model.live="branch">
                                        <option value="">All my branches</option>
                                        @foreach ($this->branches as $option)
                                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </label>
                        @endif
                        <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                            @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This week', 'month' => 'This month', 'last-month' => 'Last month'] as $key => $label)
                                <x-filament::button size="sm" color="gray" wire:click="range('{{ $key }}')">{{ $label }}</x-filament::button>
                            @endforeach
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <x-filament::button tag="a" :href="$this->exportUrl('xlsx')" icon="heroicon-m-table-cells" color="success">Excel</x-filament::button>
                        <x-filament::button tag="a" :href="$this->exportUrl('pdf')" icon="heroicon-m-document-arrow-down" color="danger">PDF</x-filament::button>
                    </div>
                </div>
                <p style="margin-top: 0.75rem; font-size: 0.8rem; opacity: 0.7;">{{ $this->available()[$report]['description'] ?? '' }} {{ $this->filters->describe() }} · Business days, each starting at the branch's cut-off time · Amounts in {{ $currency }}</p>
            </x-filament::section>

            @if ($table->highlights !== [])
                <div style="display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));">
                    @foreach ($table->highlights as $highlight)
                        <x-filament::section compact>
                            <p style="font-size: 0.8rem; opacity: 0.7;">{{ $highlight['label'] }}</p>
                            <p style="font-size: 1.5rem; font-weight: 700;">{{ ($highlight['type'] ?? '') === 'money' ? $currency.' ' : '' }}{{ $format($highlight['value'], $highlight['type'] ?? 'text') }}</p>
                        </x-filament::section>
                    @endforeach
                </div>
            @endif

            @if ($table->chart && $table->rows !== [])
                @livewire(\App\Filament\Widgets\ReportChart::class, ['chart' => $table->chart], key('chart-'.md5(json_encode($table->chart))))
            @endif

            <x-filament::section :heading="$table->title">
                <div style="overflow-x: auto; max-width: 100%; -webkit-overflow-scrolling: touch;">
                    <table style="width: 100%; min-width: max-content; border-collapse: collapse; font-size: 0.875rem;">
                        <thead>
                            <tr>
                                @foreach ($table->columns as $column)
                                    <th scope="col" style="{{ $cell }} text-align: {{ $numeric($column['type']) ? 'right' : 'left' }}; font-weight: 600; white-space: nowrap;">{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($table->rows as $row)
                                <tr>
                                    @foreach ($table->columns as $column)
                                        <td style="{{ $cell }} text-align: {{ $numeric($column['type']) ? 'right' : 'left' }}; {{ $numeric($column['type']) ? 'font-variant-numeric: tabular-nums;' : '' }}">{{ $format($row[$column['key']] ?? '', $column['type']) }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($table->columns) }}" style="{{ $cell }} text-align: center; opacity: 0.7;">Nothing in this period.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($table->totals !== null && $table->rows !== [])
                            <tfoot>
                                <tr>
                                    @foreach ($table->columns as $column)
                                        <td style="{{ $cell }} border-top: 2px solid rgb(107 114 128 / 0.4); font-weight: 700; text-align: {{ $numeric($column['type']) ? 'right' : 'left' }}; font-variant-numeric: tabular-nums;">{{ $format($table->totals[$column['key']] ?? '', $column['type'] === 'date' ? 'text' : $column['type']) }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                @if ($table->note)
                    <p style="margin-top: 0.75rem; font-size: 0.8rem; opacity: 0.7;">{{ $table->note }}</p>
                @endif
            </x-filament::section>
    </div>
</x-filament-panels::page>
