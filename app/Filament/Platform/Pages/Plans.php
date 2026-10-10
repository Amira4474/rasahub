<?php

namespace App\Filament\Platform\Pages;

use App\Enums\Plan;
use App\Models\Subscription;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * What each subscription plan allows: its limits and its features, side by
 * side. The plans are defined in config/plans.php, so this page only shows
 * them.
 */
class Plans extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.platform.plans';

    public static function canAccess(): bool
    {
        return auth()->user()->can('viewAny', Subscription::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tenants = Subscription::query()
            ->selectRaw('plan, count(*) as tenants')
            ->groupBy('plan')
            ->pluck('tenants', 'plan');

        return [
            'plans' => Plan::cases(),
            'limits' => config('plans.limits'),
            'features' => config('plans.features'),
            'prices' => collect(config('marketing.plans'))->keyBy(fn (array $plan): string => strtolower($plan['name'])),
            'tenants' => $tenants,
        ];
    }
}
