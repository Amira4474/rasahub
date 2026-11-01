<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Category')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        // Only one level of nesting, so only top-level categories can
                        // be a parent, and a category with its own sub-categories
                        // cannot be moved under another.
                        Select::make('parent_id')
                            ->label('Inside category')
                            ->helperText('Optional. For example, Coffee inside Drinks.')
                            ->relationship(
                                'parent',
                                'name',
                                modifyQueryUsing: fn (Builder $query, ?Category $record): Builder => $query
                                    ->whereNull('parent_id')
                                    ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey())),
                            )
                            ->disabled(fn (?Category $record): bool => $record?->children()->exists() ?? false)
                            ->searchable()
                            ->preload(),
                        Select::make('station_id')
                            ->label('Made at')
                            ->helperText('The station its products go to: on kitchen screens and tickets. A product can choose another.')
                            ->relationship('station', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                            ->preload(),
                        ColorPicker::make('colour')
                            ->helperText('The colour of its buttons on the POS.')
                            ->regex('/^#[0-9A-Fa-f]{6}$/'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('An inactive category and its products are hidden from the POS.')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
