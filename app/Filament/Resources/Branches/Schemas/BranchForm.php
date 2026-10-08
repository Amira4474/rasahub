<?php

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->helperText('Short code, unique within the business.')
                    ->required()
                    ->alphaDash()
                    ->maxLength(32)
                    ->unique(),
                Select::make('timezone')
                    ->helperText('Leave empty to use the business timezone.')
                    ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                    ->searchable(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
