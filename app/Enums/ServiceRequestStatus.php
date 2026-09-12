<?php

namespace App\Enums;

/**
 * Whether a Service Request is still open or has been closed.
 */
enum ServiceRequestStatus: string
{
    case Open = 'open';
    case Close = 'close';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Close => 'Close',
        };
    }
}
