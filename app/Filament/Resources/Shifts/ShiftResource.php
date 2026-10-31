<?php

namespace App\Filament\Resources\Shifts;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Cash drawer sessions: the float, the cash expected, the cash counted and
 * the difference.
 */
class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        $money = fn (?string $state): ?string => $state === null ? null : OrderResource::money($state);
        $name = fn (?int $id): ?string => $id === null ? null : (User::acrossBusinesses()->find($id)?->name ?? 'Removed user');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['branch', 'terminal']))
            ->defaultSort('opened_at', 'desc')
            ->columns([
                TextColumn::make('terminal.name')
                    ->label('Terminal')
                    ->description(fn (Shift $record): string => $record->branch->name),
                TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime()
                    ->description(fn (Shift $record): ?string => $name($record->opened_by))
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Closed')
                    ->dateTime()
                    ->description(fn (Shift $record): ?string => $name($record->closed_by))
                    ->placeholder('Still open'),
                TextColumn::make('opening_float')
                    ->label('Float')
                    ->formatStateUsing($money)
                    ->alignEnd(),
                TextColumn::make('expected_cash')
                    ->label('Expected')
                    ->formatStateUsing($money)
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('counted_cash')
                    ->label('Counted')
                    ->formatStateUsing($money)
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('variance')
                    ->label('Difference')
                    ->formatStateUsing($money)
                    ->color(fn (?string $state): string => match (true) {
                        $state === null => 'gray',
                        bccomp($state, '0', 2) === 0 => 'success',
                        default => 'danger',
                    })
                    ->tooltip(fn (Shift $record): ?string => $record->closing_note)
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('branch')
                    ->relationship('branch', 'name', fn (Builder $query): Builder => $query->visibleTo(auth()->user()))
                    ->preload(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', Branch::visibleTo(auth()->user())->select('id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShifts::route('/'),
        ];
    }
}
