<?php

namespace App\Enums;

/**
 * What a discount is given on: one line, or the whole order.
 */
enum DiscountScope: string
{
    case Item = 'item';
    case Order = 'order';

    public function label(): string
    {
        return match ($this) {
            self::Item => 'One item',
            self::Order => 'Whole order',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(array_map(fn (self $scope): array => [$scope->value, $scope->label()], self::cases()), 1, 0);
    }
}
