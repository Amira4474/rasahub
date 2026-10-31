<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Reports\ReportFilters;
use App\Reports\Reports;
use App\Tenancy\TenantContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Today's business so far, against the same day last week.
 */
class TodaySales extends StatsOverviewWidget
{
    protected static ?int $sort = -5;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return app(TenantContext::class)->business() !== null && auth()->user()->can(Permission::ViewReports);
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $today = ReportFilters::today($user);
        $lastWeek = $today->subWeek();

        $now = app(Reports::class)->run('summary', ReportFilters::for($user, $today->toDateString(), $today->toDateString(), null))->totals;
        $then = app(Reports::class)->run('summary', ReportFilters::for($user, $lastWeek->toDateString(), $lastWeek->toDateString(), null))->totals;

        $currency = app(TenantContext::class)->business()->currency;
        $average = fn (array $t): float => $t['orders'] > 0 ? (float) $t['total'] / $t['orders'] : 0.0;

        return [
            $this->compare('Net takings today', (float) $now['net'], (float) $then['net'], fn (float $v): string => $currency.' '.number_format($v, 2)),
            $this->compare('Orders today', (float) $now['orders'], (float) $then['orders'], fn (float $v): string => number_format($v)),
            $this->compare('Average spend today', $average($now), $average($then), fn (float $v): string => $currency.' '.number_format($v, 2)),
        ];
    }

    private function compare(string $label, float $now, float $then, callable $format): Stat
    {
        $stat = Stat::make($label, $format($now));

        if ($then <= 0) {
            return $stat->description('Nothing on this day last week');
        }

        $change = ($now - $then) / $then * 100;

        return $stat
            ->description(sprintf('%s%.0f%% on this day last week (%s)', $change >= 0 ? '+' : '', $change, $format($then)))
            ->descriptionIcon($change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($change >= 0 ? 'success' : 'danger');
    }
}
