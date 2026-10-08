<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Accepted => 'success',
            self::Expired => 'gray',
            self::Revoked => 'danger',
        };
    }
}
