<?php

namespace App\Enums;

enum TableShape: string
{
    case Square = 'square';
    case Round = 'round';
    case Rectangle = 'rectangle';

    public function label(): string
    {
        return match ($this) {
            self::Square => 'Square',
            self::Round => 'Round',
            self::Rectangle => 'Rectangle',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $shape): array => [$shape->value, $shape->label()], self::cases()),
            1,
            0,
        );
    }
}
