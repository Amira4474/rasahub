<?php

namespace App\Enums;

enum ProductType: string
{
    // An item on its own, such as Nasi Lemak.
    case Single = 'single';
    // A set meal, made up of other products chosen when it is ordered.
    case Set = 'set';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single item',
            self::Set => 'Set meal',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $type): array => [$type->value, $type->label()], self::cases()),
            1,
            0,
        );
    }
}
