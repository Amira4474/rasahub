<?php

namespace App\Enums;

/**
 * The subscription plans. They match the plans on the website, whose
 * prices and descriptions are in config/marketing.php.
 */
enum Plan: string
{
    case Starter = 'starter';
    case Growth = 'growth';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Growth => 'Growth',
            self::Enterprise => 'Enterprise',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $plan): array => [$plan->value, $plan->label()], self::cases()),
            1,
            0,
        );
    }
}
