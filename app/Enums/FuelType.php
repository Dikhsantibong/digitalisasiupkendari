<?php

namespace App\Enums;

use App\Models\Machine;

/**
 * The fuel a generating machine consumes.
 *
 * Stored on the master {@see Machine} record; the operator must
 * complete it per machine because the source Excel never modelled it.
 */
enum FuelType: string
{
    case HsdMfo = 'hsd_mfo';
    case HsdOnly = 'hsd_only';

    public function label(): string
    {
        return match ($this) {
            self::HsdMfo => 'HSD + MFO',
            self::HsdOnly => 'HSD saja',
        };
    }

    public function usesMfo(): bool
    {
        return $this === self::HsdMfo;
    }
}
