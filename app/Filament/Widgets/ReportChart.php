<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

/**
 * The chart above a report, drawn with the chart library Filament already
 * includes. Used on the Reports page only, so it is not on the dashboard.
 */
class ReportChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '18rem';

    /**
     * @var array{type: string, labels: list<string>, series: array<string, list<float>>}
     */
    public array $chart = ['type' => 'bar', 'labels' => [], 'series' => []];

    protected function getType(): string
    {
        return $this->chart['type'];
    }

    protected function getData(): array
    {
        $palette = ['#f59e0b', '#3d8a67', '#dc4a2d', '#6366f1', '#0ea5e9', '#a855f7', '#ec4899', '#84cc16', '#14b8a6', '#f97316'];

        return [
            'labels' => $this->chart['labels'],
            'datasets' => collect($this->chart['series'])->map(fn (array $values, string $label): array => [
                'label' => $label,
                'data' => $values,
                'backgroundColor' => $this->chart['type'] === 'doughnut' ? array_slice($palette, 0, count($values)) : '#f59e0b',
                'borderColor' => $this->chart['type'] === 'line' ? '#f59e0b' : null,
                'fill' => false,
                'tension' => 0.3,
            ])->values()->all(),
        ];
    }
}
