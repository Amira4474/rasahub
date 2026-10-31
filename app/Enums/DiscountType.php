<?php

namespace App\Enums;

enum DiscountType: string
{
    case Percent = 'percent';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentage',
            self::Amount => 'Fixed amount',
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
