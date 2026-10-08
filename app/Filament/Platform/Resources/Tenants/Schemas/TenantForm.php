<?php

namespace App\Filament\Platform\Resources\Tenants\Schemas;

use App\Filament\Platform\Resources\Subscriptions\Schemas\SubscriptionForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->alphaDash()
                    ->maxLength(255)
                    ->unique(),
                TextInput::make('acronym')
                    ->helperText('Staff type this to find their company when signing in to the POS. 2 to 10 letters or digits.')
                    ->required()
                    ->alphaNum()
                    ->minLength(2)
                    ->maxLength(10)
                    ->dehydrateStateUsing(fn (string $state): string => strtoupper($state))
                    ->unique(),
                TextInput::make('currency')
                    ->helperText('Three-letter ISO 4217 code, for example MYR.')
                    ->required()
                    ->alpha()
                    ->length(3)
                    ->dehydrateStateUsing(fn (string $state): string => strtoupper($state)),
                Select::make('timezone')
                    ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                    ->searchable()
                    ->required()
                    ->default('UTC'),
                Toggle::make('is_active')
                    ->helperText('Nobody in an inactive business can sign in.')
                    ->default(true),
                // Set once here; afterwards it is managed under Subscriptions.
                Section::make('Subscription')
                    ->relationship('subscription')
                    ->visibleOn('create')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema(SubscriptionForm::fields()),
            ]);
    }
}
