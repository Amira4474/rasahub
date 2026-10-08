<?php

namespace App\Filament\Resources\Businesses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BusinessForm
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
                    ->default(true),
            ]);
    }
}
