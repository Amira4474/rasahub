<?php

namespace App\Enums;

enum PrintJobStatus: string
{
    // Waiting for the print gateway.
    case Queued = 'queued';
    // Taken by the gateway, not yet confirmed.
    case Printing = 'printing';
    case Printed = 'printed';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Printing => 'info',
            self::Printed => 'success',
            self::Failed => 'danger',
        };
    }
}
