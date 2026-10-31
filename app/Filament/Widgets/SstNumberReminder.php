<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\RestaurantSettings;
use App\Models\RestaurantSetting;
use App\Tenancy\TenantContext;
use Filament\Widgets\Widget;

/**
 * Shown on the dashboard while SST is charged but no registration number
 * has been entered to print on receipts.
 */
class SstNumberReminder extends Widget
{
    protected static ?int $sort = -10;

    // Shown with the page rather than loaded after it, so it cannot be missed.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.sst-number-reminder';

    public static function canView(): bool
    {
        $business = app(TenantContext::class)->business();

        return $business !== null
            && auth()->user()->can('update', $business)
            && RestaurantSetting::current()->lacksSstNumber();
    }

    protected function getViewData(): array
    {
        return ['url' => RestaurantSettings::getUrl()];
    }
}
