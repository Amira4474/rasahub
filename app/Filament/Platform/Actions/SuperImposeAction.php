<?php

namespace App\Filament\Platform\Actions;

use App\Enums\Permission;
use App\Models\Business;
use App\Tenancy\SuperImpose;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Enters a tenant as the signed-in platform user: its admin pages, or its
 * POS. Used on the tenant list and on each tenant's page.
 */
class SuperImposeAction
{
    /**
     * Both ways in, under one "Super Impose" button.
     */
    public static function group(): ActionGroup
    {
        return ActionGroup::make([self::admin(), self::pos()])
            ->label('Super Impose')
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->color('danger')
            ->button()
            ->visible(fn (): bool => auth()->user()->can(Permission::StartSuperImpose));
    }

    public static function admin(): Action
    {
        return self::make(
            'superImpose',
            SuperImpose::INTO_ADMIN,
            label: 'Tenant admin',
            icon: Heroicon::OutlinedCog6Tooth,
            description: 'You will enter this tenant\'s admin pages with full access, as yourself.',
        );
    }

    public static function pos(): Action
    {
        return self::make(
            'superImposePos',
            SuperImpose::INTO_POS,
            label: 'POS',
            icon: Heroicon::OutlinedComputerDesktop,
            description: 'You will enter this tenant\'s POS with full access, as yourself, and choose a terminal. Orders and payments you take there are real and recorded as yours.',
        );
    }

    private static function make(string $name, string $into, string $label, Heroicon $icon, string $description): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()->can(Permission::StartSuperImpose))
            ->modalHeading(fn (Business $record): string => "Super Impose into {$record->name}: {$label}")
            ->modalDescription($description.' Everything you do there is your own action. It lasts until you exit or sign out.')
            ->modalSubmitActionLabel('Enter tenant')
            ->schema([
                Textarea::make('reason')
                    ->helperText('Optional. Recorded in the audit log.')
                    ->maxLength(500),
            ])
            ->action(function (Business $record, array $data, Component $livewire) use ($into): void {
                $superImpose = app(SuperImpose::class);
                $superImpose->start($record, $data['reason'] ?? null, $into);

                session()->regenerate();

                $livewire->redirect($superImpose->entryUrl());
            });
    }
}
