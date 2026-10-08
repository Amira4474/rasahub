<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Terminal;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * What the signed-in user can reach in the current business.
 */
class BusinessOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();

        $stats = [
            Stat::make('Branches', Branch::visibleTo($user)->count()),
            Stat::make('POS terminals', Terminal::visibleTo($user)->count()),
        ];

        if ($user->can(Permission::ViewUsers)) {
            $stats[] = Stat::make('Staff', User::count());
        }

        return $stats;
    }
}
