<?php

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Only platform users pick a business; for everyone else the
                // create page sets it to their own. It cannot change later.
                Select::make('business_id')
                    ->relationship('business', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn (): bool => auth()->user()->isPlatformUser())
                    ->disabledOn('edit'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->helperText('Short code, unique within the business.')
                    ->required()
                    ->alphaDash()
                    ->maxLength(32)
                    ->unique(modifyRuleUsing: fn (Unique $rule, Get $get, ?object $record): Unique => $rule->where(
                        'business_id',
                        $record?->business_id ?? $get('business_id') ?? auth()->user()->business_id,
                    )),
                Select::make('timezone')
                    ->helperText('Leave empty to use the business timezone.')
                    ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                    ->searchable(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
