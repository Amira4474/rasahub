<?php

namespace App\Filament\Resources\FloorAreas\Tables;

use App\Filament\Resources\FloorAreas\FloorAreaResource;
use App\Models\FloorArea;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FloorAreasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('branch')->withSum('tables', 'seats'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('tables_count')
                    ->counts('tables')
                    ->label('Tables'),
                TextColumn::make('tables_sum_seats')
                    ->label('Seats')
                    ->placeholder('0'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('branch')
                    ->relationship('branch', 'name', fn (Builder $query): Builder => $query->visibleTo(auth()->user()))
                    ->preload(),
            ])
            ->recordActions([
                Action::make('plan')
                    ->label('Floor plan')
                    ->icon(Heroicon::OutlinedMap)
                    ->url(fn (FloorArea $record): string => FloorAreaResource::getUrl('plan', ['record' => $record])),
                Action::make('qrCodes')
                    ->label('QR codes')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->visible(fn (): bool => FloorAreaResource::qrOrderingIncluded())
                    ->url(fn (FloorArea $record): string => route('qr.codes', $record->ulid), shouldOpenInNewTab: true),
                EditAction::make(),
            ]);
    }
}
