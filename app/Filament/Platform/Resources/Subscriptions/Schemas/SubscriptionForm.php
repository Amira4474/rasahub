<?php

namespace App\Filament\Platform\Resources\Subscriptions\Schemas;

use App\Enums\Plan;
use App\Models\Subscription;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Subscription')
                    ->columns(3)
                    ->schema(self::fields()),
            ]);
    }

    /**
     * The fields are also used when a tenant is created.
     *
     * @return list<Component>
     */
    public static function fields(): array
    {
        return [
            Select::make('plan')
                ->options(Plan::options())
                ->default(Plan::Starter->value)
                ->selectablePlaceholder(false)
                ->required(),
            DatePicker::make('starts_at')
                ->label('Start date')
                ->default(fn (): string => Subscription::firstPeriod()['starts_at']->toDateString())
                ->required(),
            DatePicker::make('ends_at')
                ->label('End date')
                ->helperText('The last day the subscription is valid.')
                ->default(fn (): string => Subscription::firstPeriod()['ends_at']->toDateString())
                ->afterOrEqual('starts_at')
                ->required(),
        ];
    }
}
