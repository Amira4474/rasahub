<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\Permission;
use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductPriceChange;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Every change of the product's price, and of its price at each branch.
 */
class PriceChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'priceChanges';

    protected static ?string $title = 'Price history';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can(Permission::ManageCatalogue);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('branch'))
            ->defaultSort('changed_at', 'desc')
            ->columns([
                TextColumn::make('changed_at')
                    ->label('When')
                    ->dateTime(),
                TextColumn::make('branch.name')
                    ->label('Price')
                    ->placeholder('Product price')
                    ->formatStateUsing(fn (string $state): string => "At {$state}"),
                TextColumn::make('old_price')
                    ->label('From')
                    ->money(fn (): string => ProductResource::currency())
                    ->placeholder('—'),
                TextColumn::make('new_price')
                    ->label('To')
                    ->money(fn (): string => ProductResource::currency())
                    ->placeholder('Removed'),
                TextColumn::make('changed_by')
                    ->label('By')
                    ->formatStateUsing(fn (ProductPriceChange $record): string => $record->changedBy()?->name ?? 'Removed user')
                    ->placeholder('System'),
            ]);
    }
}
