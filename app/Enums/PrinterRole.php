<?php

namespace App\Enums;

enum PrinterRole: string
{
    // Receipts, refund slips and shift reports at a POS terminal.
    case Receipt = 'receipt';
    // Tickets for the stations it serves.
    case Kitchen = 'kitchen';
    case Bar = 'bar';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipts',
            self::Kitchen => 'Kitchen tickets',
            self::Bar => 'Bar tickets',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(array_map(fn (self $role): array => [$role->value, $role->label()], self::cases()), 1, 0);
    }
}
