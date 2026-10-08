<?php

namespace App\Filament\Platform\Resources\Tenants\Tables;

use App\Filament\Platform\Actions\SuperImposeAction;
use App\Models\Business;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('subscription'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('acronym')
                    ->searchable(),
                TextColumn::make('plan')
                    ->state(fn (Business $record): ?string => $record->subscription?->plan->label())
                    ->placeholder('None'),
                TextColumn::make('subscription_status')
                    ->label('Subscription')
                    ->state(fn (Business $record): ?string => $record->subscription?->status()->label())
                    ->badge()
                    ->color(fn (Business $record): string => $record->subscription?->status()->color() ?? 'gray')
                    ->placeholder('None'),
                TextColumn::make('subscription.ends_at')
                    ->label('Ends')
                    ->date(),
                // A platform user reaches no tenant users by default, so
                // this count deliberately reads across businesses.
                TextColumn::make('users_count')
                    ->counts(['users' => fn (Builder $query): Builder => $query->acrossBusinesses()])
                    ->label('Users'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('currency')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('timezone')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                SuperImposeAction::make(),
                EditAction::make(),
            ]);
    }
}
