<?php

namespace App\Filament\Platform\Resources\Tenants\Schemas;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Terminal;
use App\Models\User;
use App\Tenancy\TenantDatabase;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Business information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('acronym')
                            ->helperText('Typed by staff at the POS sign-in.'),
                        TextEntry::make('slug'),
                        TextEntry::make('currency'),
                        TextEntry::make('timezone'),
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('ulid')
                            ->label('ULID')
                            ->copyable(),
                    ]),
                Section::make('Subscription')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('plan')
                            ->state(fn (Business $record): ?string => $record->subscription?->plan->label())
                            ->placeholder('None'),
                        TextEntry::make('subscription_status')
                            ->label('Status')
                            ->state(fn (Business $record): ?string => $record->subscription?->status()->label())
                            ->badge()
                            ->color(fn (Business $record): string => $record->subscription?->status()->color() ?? 'gray')
                            ->placeholder('None'),
                        TextEntry::make('subscription.starts_at')
                            ->label('Start date')
                            ->date()
                            ->placeholder('Not set'),
                        TextEntry::make('subscription.ends_at')
                            ->label('End date')
                            ->date()
                            ->placeholder('Not set'),
                        TextEntry::make('subscription.suspension_reason')
                            ->label('Suspension reason')
                            ->visible(fn (Business $record): bool => $record->subscription?->suspended_at !== null)
                            ->placeholder('No reason given')
                            ->columnSpanFull(),
                    ]),
                // The counts are read from inside the tenant's own database.
                Section::make('Data')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('database')
                            ->state(fn (Business $record): string => app(TenantDatabase::class)->nameFor($record))
                            ->copyable(),
                        TextEntry::make('branches')
                            ->state(fn (Business $record): int => $record->run(fn (): int => Branch::count())),
                        TextEntry::make('terminals')
                            ->label('POS terminals')
                            ->state(fn (Business $record): int => $record->run(fn (): int => Terminal::count())),
                        TextEntry::make('staff')
                            ->state(fn (Business $record): int => User::acrossBusinesses()->where('business_id', $record->getKey())->count()),
                    ]),
            ]);
    }
}
