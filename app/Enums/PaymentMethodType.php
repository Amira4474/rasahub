<?php

namespace App\Enums;

enum PaymentMethodType: string
{
    // Goes into the cash drawer; change is given.
    case Cash = 'cash';
    // Taken on the restaurant's own card terminal.
    case Card = 'card';
    // The customer scans the restaurant's QR code, shown on the POS.
    case Qr = 'qr';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Qr => 'QR payment',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(array_map(fn (self $type): array => [$type->value, $type->label()], self::cases()), 1, 0);
    }
}
