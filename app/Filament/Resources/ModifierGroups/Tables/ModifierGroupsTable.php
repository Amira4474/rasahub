<?php

namespace App\Filament\Resources\ModifierGroups\Tables;

use App\Models\ModifierGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ModifierGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('modifiers'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('rule')
                    ->state(fn (ModifierGroup $record): string => $record->rule()),
                TextColumn::make('options')
                    ->state(fn (ModifierGroup $record): string => $record->modifiers->pluck('name')->implode(', '))
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('It is removed from every product it is attached to. Past orders keep their own copy of the options chosen.'),
            ]);
    }
}
