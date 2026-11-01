<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use App\Enums\PaymentMethodType;
use App\Tenancy\TenantContext;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        $type = fn (Get $get): ?PaymentMethodType => $get('type') instanceof PaymentMethodType ? $get('type') : PaymentMethodType::tryFrom((string) $get('type'));

        return $schema
            ->columns(1)
            ->components([
                Section::make('Payment method')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->helperText('As cashiers see it on the POS, for example DuitNow QR or Touch \'n Go.')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->helperText('Cash goes into the drawer and gives change. Card and QR payments are taken on your own terminal or QR code; RasaHub records them.')
                            ->options(PaymentMethodType::options())
                            ->selectablePlaceholder(false)
                            ->default(PaymentMethodType::Qr->value)
                            ->live()
                            ->required(),
                        // Shown on the POS screen for the customer to scan.
                        FileUpload::make('qr_image_path')
                            ->label('QR code image')
                            ->helperText('The QR code customers scan to pay. The POS shows it on screen when the cashier chooses this method.')
                            ->image()
                            ->disk('public')
                            ->directory(fn (): string => 'tenants/'.strtolower((string) app(TenantContext::class)->business()?->acronym).'/payment-qr')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->visible(fn (Get $get): bool => $type($get) === PaymentMethodType::Qr)
                            ->columnSpanFull(),
                        Toggle::make('requires_reference')
                            ->label('Ask for a reference')
                            ->helperText('The cashier must enter the approval code or transaction ID.')
                            ->visible(fn (Get $get): bool => $type($get) !== PaymentMethodType::Cash)
                            ->inline(false),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('An inactive method is not offered on the POS.')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
