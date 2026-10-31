<?php

namespace App\Filament\Resources\Branches\Schemas;

use App\Tenancy\TenantContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Branch')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->helperText('Short code, unique within the business.')
                            ->required()
                            ->alphaDash()
                            ->maxLength(32)
                            ->unique(),
                        Select::make('timezone')
                            ->helperText('Leave empty to use the business timezone.')
                            ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                            ->searchable(),
                        Toggle::make('is_active')
                            ->default(true)
                            ->inline(false),
                    ]),
                Section::make('Receipts')
                    ->description('Printed under the restaurant\'s receipt header.')
                    ->columns(2)
                    ->schema([
                        Textarea::make('address')
                            ->rows(3)
                            ->maxLength(500),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(32),
                    ]),
                Section::make('QR ordering')
                    ->description('Customers order from their phones by scanning the QR code on their table. Growth and Enterprise.')
                    ->visible(fn (): bool => app(TenantContext::class)->business()?->subscription?->plan->includes('qr_ordering') ?? false)
                    ->columns(2)
                    ->schema([
                        Toggle::make('qr_ordering_enabled')
                            ->label('Take orders by QR code')
                            ->default(true),
                        Toggle::make('qr_auto_confirm')
                            ->label('Send QR orders to the kitchen without confirming')
                            ->helperText('Off: staff confirm each QR order at the POS first.'),
                    ]),
                Section::make('Service charge and business day')
                    ->columns(2)
                    ->schema([
                        TextInput::make('service_charge_rate')
                            ->label('Service charge')
                            ->helperText('0 for none. Worked out on the amount before SST; SST is then charged on it too.')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default('0.00')
                            ->live(onBlur: true)
                            ->required(),
                        Toggle::make('service_charge_dine_in_only')
                            ->label('Dine-in only')
                            ->helperText('Takeaway orders are not charged.')
                            ->default(true)
                            ->inline(false)
                            ->visible(fn (Get $get): bool => (float) $get('service_charge_rate') > 0),
                        TextInput::make('kitchen_amber_minutes')
                            ->label('Kitchen screen: amber after')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(240)
                            ->suffix('minutes')
                            ->default(10)
                            ->required(),
                        TextInput::make('kitchen_red_minutes')
                            ->label('Kitchen screen: red after')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(240)
                            ->gte('kitchen_amber_minutes')
                            ->suffix('minutes')
                            ->default(15)
                            ->required(),
                        TimePicker::make('business_day_starts_at')
                            ->label('Business day starts at')
                            ->helperText('Sales before this time count towards the previous day. With 04:00, a sale at 01:30 on Saturday is in Friday\'s report.')
                            ->seconds(false)
                            ->default('04:00')
                            ->required(),
                    ]),
            ]);
    }
}
