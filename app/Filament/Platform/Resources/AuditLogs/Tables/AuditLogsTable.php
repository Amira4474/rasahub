<?php

namespace App\Filament\Platform\Resources\AuditLogs\Tables;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(fn (AuditEvent $state): string => $state->label()),
                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('Removed user')
                    ->searchable(),
                TextColumn::make('business.name')
                    ->label('Tenant')
                    ->placeholder('Platform'),
                IconColumn::make('super_impose')
                    ->label('Super Impose')
                    ->boolean(),
                TextColumn::make('details')
                    ->state(fn (AuditLog $record): ?string => $record->properties === null
                        ? null
                        : collect($record->properties)
                            ->map(fn (mixed $value, string $key): string => $key.': '.(is_array($value) ? implode(', ', $value) : $value))
                            ->implode(' · '))
                    ->wrap(),
                TextColumn::make('ip_address')
                    ->label('IP address')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
