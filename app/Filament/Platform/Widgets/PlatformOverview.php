<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\TenantInvitation;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Totals across every tenant, from the central database.
 */
class PlatformOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $running = Subscription::whereNull('suspended_at')->whereDate('ends_at', '>=', today());

        return [
            Stat::make('Tenants', Business::count())
                ->description(Business::where('is_active', true)->count().' active'),
            Stat::make('Active subscriptions', (clone $running)->count())
                ->description((clone $running)->whereDate('ends_at', '<=', today()->addDays(7))->count().' ending within 7 days'),
            Stat::make('Suspended or expired', Subscription::count() - (clone $running)->count()),
            // Tenant users are out of a platform user's reach by default,
            // so this count deliberately reads across businesses.
            Stat::make('Tenant users', User::acrossBusinesses()->whereNotNull('business_id')->count()),
            Stat::make('Pending invitations', TenantInvitation::whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count()),
        ];
    }
}
