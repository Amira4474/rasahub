<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['branch', 'diningTable', 'customer']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number_label')
                    ->label('Order')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('business_date')
                    ->label('Business day')
                    ->date()
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->toggleable(),
                TextColumn::make('place')
                    ->state(fn (Order $record): string => $record->placeLabel()),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->state(fn (Order $record): ?string => $record->customer?->displayName())
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color()),
                TextColumn::make('total')
                    ->formatStateUsing(fn (string $state): string => OrderResource::money($state))
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('branch')
                    ->relationship('branch', 'name', fn (Builder $query): Builder => $query->visibleTo(auth()->user()))
                    ->preload(),
                SelectFilter::make('status')
                    ->options(OrderStatus::options()),
                SelectFilter::make('type')
                    ->options(OrderType::options()),
                Filter::make('business_date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('business_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('business_date', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
