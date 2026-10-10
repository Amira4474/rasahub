<?php

namespace App\Enums;

/**
 * The subscription plans. They match the plans on the website, whose
 * prices and descriptions are in config/marketing.php. What each plan
 * allows is in config/plans.php.
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
     * The most of something a tenant on this plan may have: "branches",
     * "terminals" or "staff". Null means unlimited.
     */
    public function limit(string $limit): ?int
    {
        return config("plans.limits.{$limit}.plans.{$this->value}");
    }

    public function includes(string $feature): bool
    {
        return in_array($this->value, config("plans.features.{$feature}.plans", []), true);
    }

    public function support(): string
    {
        return config("plans.support.{$this->value}");
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
