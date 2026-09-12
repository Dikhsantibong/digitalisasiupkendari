<?php

namespace App\Enums;

/**
 * Where a Work Order / Service Request came from. Manual entry now; the WPC
 * integration will produce `wpc` rows later, living alongside manual ones.
 */
enum WorkOrderSource: string
{
    case Manual = 'manual';
    case Wpc = 'wpc';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Wpc => 'WPC',
        };
    }
}
