<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Filament\Pages\Reports as ReportsPage;
use App\Reports\ReportFilters;
use App\Reports\Reports;
use App\Tenancy\TenantContext;
use Filament\Widgets\Widget;

/**
 * Today's five best-selling products.
 */
class BestSellers extends Widget
{
    protected static ?int $sort = -3;

    protected string $view = 'filament.widgets.best-sellers';

    public static function canView(): bool
    {
        return app(TenantContext::class)->business() !== null && auth()->user()->can(Permission::ViewReports);
    }

    protected function getViewData(): array
    {
        $today = ReportFilters::today(auth()->user())->toDateString();
        $table = app(Reports::class)->run('products', ReportFilters::for(auth()->user(), $today, $today, null));

        return [
            'rows' => array_slice($table->rows, 0, 5),
            'currency' => app(TenantContext::class)->business()->currency,
            'url' => ReportsPage::getUrl(['report' => 'products', 'from' => $today, 'to' => $today]),
        ];
    }
}
