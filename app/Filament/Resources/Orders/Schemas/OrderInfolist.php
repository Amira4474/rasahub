<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (?string $state): string => OrderResource::money($state);

        return $schema
            ->columns(1)
            ->components([
                Section::make('Order')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('number_label')->label('Order'),
                        TextEntry::make('receipt')
                            ->state(fn (Order $record): ?string => $record->receiptLabel())
                            ->placeholder('Not paid yet'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                            ->color(fn (OrderStatus $state): string => $state->color()),
                        TextEntry::make('payment_status')
                            ->label('Payment')
                            ->badge()
                            ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                            ->color(fn (PaymentStatus $state): string => $state->color()),
                        TextEntry::make('branch.name')->label('Branch'),
                        TextEntry::make('place')->state(fn (Order $record): string => $record->placeLabel()),
                        TextEntry::make('guests')->placeholder('—'),
                        TextEntry::make('business_date')->label('Business day')->date(),
                        TextEntry::make('customer')
                            ->state(fn (Order $record): ?string => $record->customer ? "{$record->customer->displayName()} ({$record->customer->phone})" : null)
                            ->placeholder('None'),
                        TextEntry::make('opened_by')
                            ->label('Taken by')
                            ->formatStateUsing(fn (int $state): string => User::acrossBusinesses()->find($state)?->name ?? 'Removed user'),
                        TextEntry::make('created_at')->label('Started')->dateTime(),
                        TextEntry::make('completed_at')->label('Paid')->dateTime()->placeholder('—'),
                    ]),
                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->formatStateUsing(fn (OrderItem $record): string => "{$record->quantity} × {$record->name}")
                                    ->weight('bold')
                                    ->columnSpan(2),
                                TextEntry::make('details')
                                    ->hiddenLabel()
                                    ->state(fn (OrderItem $record): ?string => collect([
                                        ...$record->modifiers->pluck('name'),
                                        ...$record->parts->pluck('name')->map(fn (string $name): string => "– {$name}"),
                                        $record->note ? "“{$record->note}”" : null,
                                    ])->filter()->implode(', ') ?: null),
                                TextEntry::make('line_total')
                                    ->hiddenLabel()
                                    ->state(fn (OrderItem $record): string => $money(bcadd($record->line_total, (string) $record->parts->sum('line_total'), 2)))
                                    ->alignEnd(),
                            ]),
                    ]),
                Section::make('Totals')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal')->formatStateUsing($money),
                        TextEntry::make('service_charge')->formatStateUsing($money),
                        TextEntry::make('sst')
                            ->label(fn (Order $record): string => 'SST'.($record->prices_include_sst ? ' (included)' : '')),
                        TextEntry::make('rounding'),
                        TextEntry::make('total')->formatStateUsing($money)->weight('bold'),
                        TextEntry::make('paid')->formatStateUsing($money),
                    ]),
                Section::make('Payments')
                    ->visible(fn (Order $record): bool => $record->payments->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('method_name')->label('Method'),
                                TextEntry::make('amount')->formatStateUsing($money),
                                TextEntry::make('reference')->placeholder('—'),
                                TextEntry::make('received_at')->label('When')->dateTime(),
                            ]),
                    ]),
            ]);
    }
}
