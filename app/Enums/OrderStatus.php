<?php

namespace App\Enums;

/**
 * Where an order stands in the restaurant. Whether it has been paid for is a
 * separate matter: see PaymentStatus.
 */
enum OrderStatus: string
{
    // Being put together; nothing sent to the kitchen yet.
    case Draft = 'draft';
    // Waiting for staff to confirm, as QR orders will.
    case Pending = 'pending';
    // Sent to the kitchen.
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    // Paid and closed.
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Confirmed => 'Sent to kitchen',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Pending => 'gray',
            self::Confirmed, self::Preparing => 'info',
            self::Ready => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(array_map(fn (self $status): array => [$status->value, $status->label()], self::cases()), 1, 0);
    }
}
