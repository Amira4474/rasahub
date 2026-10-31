<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Reports\ReportFilters;
use App\Reports\Reports;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Net takings for each of the last 30 business days.
 */
class SalesTrend extends ChartWidget
{
    protected static ?int $sort = -4;

    protected ?string $heading = 'Net takings, last 30 days';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '16rem';

    public static function canView(): bool
    {
        return app(TenantContext::class)->business() !== null && auth()->user()->can(Permission::ViewReports);
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $today = ReportFilters::today(auth()->user());
        $table = app(Reports::class)->run('summary', ReportFilters::for(auth()->user(), $today->subDays(29)->toDateString(), $today->toDateString(), null));

        return [
            'labels' => array_map(fn (string $day): string => CarbonImmutable::parse($day)->format('j M'), array_column($table->rows, 'day')),
            'datasets' => [[
                'label' => 'Net takings',
                'data' => array_map('floatval', array_column($table->rows, 'net')),
                'borderColor' => '#f59e0b',
                'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                'fill' => true,
                'tension' => 0.3,
            ]],
        ];
    }
}
