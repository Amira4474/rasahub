<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use App\Enums\PaymentMethodType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('type')
                    ->formatStateUsing(fn (PaymentMethodType $state): string => $state->label()),
                ImageColumn::make('qr_image_path')
                    ->label('QR code')
                    ->disk('public'),
                IconColumn::make('requires_reference')
                    ->label('Reference')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('It is no longer offered on the POS. Past payments keep its name.'),
            ]);
    }
}
