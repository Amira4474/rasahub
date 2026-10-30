<?php

namespace App\Filament\Resources\ModifierGroups\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ModifierGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        TextInput::make('name')
                            ->helperText('For example: Size, Sugar level, Add-ons.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        TextInput::make('min_select')
                            ->label('Minimum choices')
                            ->helperText('0 makes the group optional.')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(20)
                            ->default(0)
                            ->required(),
                        TextInput::make('max_select')
                            ->label('Maximum choices')
                            ->helperText('1 means one choice only.')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(20)
                            ->gte('min_select')
                            ->default(1)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
                Repeater::make('modifiers')
                    ->label('Options')
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->reorderable()
                    ->columns(4)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Add option')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        TextInput::make('price_delta')
                            ->label('Price change')
                            ->helperText('Added to the product price. May be 0 or negative.')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(-99999)
                            ->maxValue(99999)
                            ->default('0.00')
                            ->required(),
                        Toggle::make('is_default')
                            ->label('Chosen by default'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    // A group cannot start with more options chosen than it
                    // allows.
                    ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        $defaults = collect($value)->where('is_default', true)->count();

                        if ($defaults > (int) $get('max_select')) {
                            $fail("Only {$get('max_select')} option(s) can be chosen, so no more than that can be chosen by default.");
                        }
                    }),
            ]);
    }
}
