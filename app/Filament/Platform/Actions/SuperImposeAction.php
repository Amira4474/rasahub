<?php

namespace App\Filament\Platform\Actions;

use App\Enums\Permission;
use App\Models\Business;
use App\Tenancy\SuperImpose;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Enters a tenant's admin pages as the signed-in platform user. Used on the
 * tenant list and on each tenant's page.
 */
class SuperImposeAction
{
    public static function make(): Action
    {
        return Action::make('superImpose')
            ->label('Super Impose')
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()->can(Permission::StartSuperImpose))
            ->modalHeading(fn (Business $record): string => "Super Impose into {$record->name}")
            ->modalDescription('You will enter this tenant\'s admin pages with full access, as yourself. Everything you do there is your own action. It lasts until you exit or sign out.')
            ->modalSubmitActionLabel('Enter tenant')
            ->schema([
                Textarea::make('reason')
                    ->helperText('Optional. Recorded in the audit log.')
                    ->maxLength(500),
            ])
            ->action(function (Business $record, array $data, Component $livewire): void {
                app(SuperImpose::class)->start($record, $data['reason'] ?? null);

                session()->regenerate();

                $livewire->redirect(Filament::getPanel('admin')->getUrl());
            });
    }
}
