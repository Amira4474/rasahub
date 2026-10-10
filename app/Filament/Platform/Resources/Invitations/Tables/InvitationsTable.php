<?php

namespace App\Filament\Platform\Resources\Invitations\Tables;

use App\Models\TenantInvitation;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvitationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['business', 'creator']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('token')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('note')
                    ->label('For')
                    ->placeholder('Not noted')
                    ->searchable(),
                TextColumn::make('status')
                    ->state(fn (TenantInvitation $record): string => $record->status()->label())
                    ->badge()
                    ->color(fn (TenantInvitation $record): string => $record->status()->color()),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('business.name')
                    ->label('Registered tenant')
                    ->placeholder('Not registered yet'),
                TextColumn::make('creator.name')
                    ->label('Invited by')
                    ->placeholder('Removed user')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
