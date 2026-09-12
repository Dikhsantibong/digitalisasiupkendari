<?php

namespace App\Enums;

/**
 * The three plan-vs-realisation matrices in the HAR report.
 */
enum MaintenanceScope: string
{
    case Har = 'har';
    case Pelumas = 'pelumas';
    case Air = 'air';

    public function label(): string
    {
        return match ($this) {
            self::Har => 'Pemeliharaan',
            self::Pelumas => 'Pelumas',
            self::Air => 'Air',
        };
    }
}
