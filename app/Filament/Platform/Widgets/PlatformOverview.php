<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\TenantInvitation;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Totals across every tenant, from the central database.
 */
class PlatformOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $running = Subscription::whereNull('suspended_at')->whereNotNull('starts_at')->whereDate('ends_at', '>=', today());

        $pending = Subscription::whereNull('suspended_at')
            ->where(fn (Builder $query): Builder => $query->whereNull('starts_at')->orWhereNull('ends_at'))
            ->count();

        return [
            Stat::make('Tenants', Business::count())
                ->description(Business::where('is_active', true)->count().' active'),
            Stat::make('Active subscriptions', (clone $running)->count())
                ->description((clone $running)->whereDate('ends_at', '<=', today()->addDays(7))->count().' ending within 7 days'),
            Stat::make('Awaiting activation', $pending)
                ->description('Registered, no dates yet'),
            Stat::make('Suspended or expired', Subscription::count() - (clone $running)->count() - $pending),
            // Tenant users are out of a platform user's reach by default,
            // so this count deliberately reads across businesses.
            Stat::make('Tenant users', User::acrossBusinesses()->whereNotNull('business_id')->count()),
            Stat::make('Pending invitations', TenantInvitation::whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count()),
        ];
    }
}
