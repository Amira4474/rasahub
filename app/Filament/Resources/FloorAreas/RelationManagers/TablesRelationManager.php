<?php

namespace App\Filament\Resources\FloorAreas\RelationManagers;

use App\Enums\TableShape;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class TablesRelationManager extends RelationManager
{
    protected static string $relationship = 'tables';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->helperText('What staff call it, for example T1 or Window 2.')
                    ->required()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule
                        ->where('floor_area_id', $this->getOwnerRecord()->getKey())
                        ->whereNull('deleted_at')),
                TextInput::make('seats')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(50)
                    ->default(4)
                    ->required(),
                Select::make('shape')
                    ->options(TableShape::options())
                    ->default(TableShape::Square->value)
                    ->selectablePlaceholder(false)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Arrange them on the floor plan.')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('seats'),
                TextColumn::make('shape')
                    ->formatStateUsing(fn (TableShape $state): string => $state->label()),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add table')
                    // New tables start in a free-looking spot, staggered.
                    ->mutateDataUsing(function (array $data): array {
                        $count = $this->getOwnerRecord()->tables()->count();

                        return [...$data, 'pos_x' => 5 + ($count % 6) * 15, 'pos_y' => 8 + intdiv($count, 6) % 5 * 18];
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
