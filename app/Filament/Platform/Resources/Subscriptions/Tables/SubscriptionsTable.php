<?php

namespace App\Filament\Platform\Resources\Subscriptions\Tables;

use App\Audit\Audit;
use App\Enums\AuditEvent;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        $canManage = fn (Subscription $record): bool => auth()->user()->can('update', $record);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('business'))
            ->defaultSort('ends_at')
            ->columns([
                TextColumn::make('business.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('business.acronym')
                    ->label('Acronym')
                    ->searchable(),
                TextColumn::make('plan')
                    ->formatStateUsing(fn (Plan $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('status')
                    ->state(fn (Subscription $record): string => $record->status()->label())
                    ->badge()
                    ->color(fn (Subscription $record): string => $record->status()->color())
                    ->tooltip(fn (Subscription $record): ?string => $record->suspension_reason),
                TextColumn::make('starts_at')
                    ->label('Start date')
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('End date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('plan')
                    ->options(Plan::options()),
                SelectFilter::make('status')
                    ->options(array_column(
                        array_map(fn (SubscriptionStatus $status): array => [$status->value, $status->label()], SubscriptionStatus::cases()),
                        1,
                        0,
                    ))
                    ->query(fn (Builder $query, array $data): Builder => match (SubscriptionStatus::tryFrom($data['value'] ?? '')) {
                        SubscriptionStatus::Suspended => $query->whereNotNull('suspended_at'),
                        SubscriptionStatus::Expired => $query->whereNull('suspended_at')->whereDate('ends_at', '<', today()),
                        SubscriptionStatus::Active => $query->whereNull('suspended_at')->whereDate('ends_at', '>=', today()),
                        null => $query,
                    }),
            ])
            ->recordActions([
                Action::make('extend')
                    ->label('Extend 1 month')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('success')
                    ->visible($canManage)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Subscription $record): string => "Extend {$record->business->name} by one month")
                    ->modalDescription('A subscription that is still running is extended from its end date. One that has ended gets a month from today.')
                    ->action(function (Subscription $record): void {
                        $from = $record->ends_at->toDateString();

                        $record->extendByOneMonth();

                        app(Audit::class)->record(AuditEvent::SubscriptionExtended, $record->business, [
                            'from' => $from,
                            'to' => $record->ends_at->toDateString(),
                        ]);
                    })
                    ->successNotificationTitle('Subscription extended'),
                Action::make('suspend')
                    ->icon(Heroicon::OutlinedPauseCircle)
                    ->color('danger')
                    ->visible(fn (Subscription $record): bool => $canManage($record) && $record->suspended_at === null)
                    ->modalHeading(fn (Subscription $record): string => "Suspend {$record->business->name}")
                    ->modalDescription('The tenant\'s staff will not be able to sign in to the admin pages or the POS until the subscription is resumed. Super Impose still works.')
                    ->modalSubmitActionLabel('Suspend')
                    ->schema([
                        Textarea::make('reason')
                            ->helperText('Optional. Recorded in the audit log.')
                            ->maxLength(500),
                    ])
                    ->action(function (Subscription $record, array $data): void {
                        $record->suspend($data['reason'] ?? null);

                        app(Audit::class)->record(AuditEvent::SubscriptionSuspended, $record->business, array_filter([
                            'reason' => $data['reason'] ?? null,
                        ]));
                    })
                    ->successNotificationTitle('Subscription suspended'),
                Action::make('resume')
                    ->icon(Heroicon::OutlinedPlayCircle)
                    ->color('success')
                    ->visible(fn (Subscription $record): bool => $canManage($record) && $record->suspended_at !== null)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Subscription $record): string => "Resume {$record->business->name}")
                    ->action(function (Subscription $record): void {
                        $record->resume();

                        app(Audit::class)->record(AuditEvent::SubscriptionResumed, $record->business);
                    })
                    ->successNotificationTitle('Subscription resumed'),
                EditAction::make(),
            ]);
    }
}
