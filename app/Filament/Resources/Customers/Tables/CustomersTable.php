<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        $completed = fn (Builder $query): Builder => $query->where('status', OrderStatus::Completed);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount(['orders' => $completed])
                ->withSum(['orders' => $completed], 'total')
                ->withMax(['orders' => $completed], 'completed_at'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->placeholder('No name')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('orders_count')
                    ->label('Visits')
                    ->sortable(),
                TextColumn::make('orders_sum_total')
                    ->label('Total spent')
                    ->formatStateUsing(fn (?string $state): string => OrderResource::money($state ?? '0'))
                    ->sortable(),
                TextColumn::make('orders_max_completed_at')
                    ->label('Last visit')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('marketing_consent')
                    ->label('Marketing')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
