<?php

namespace App\Enums;

/**
 * Which measurement a calibration factor scales when converting a meter delta
 * into a physical quantity.
 */
enum CalibrationFactorType: string
{
    case Kwh = 'kwh';
    case Hsd = 'hsd';
    case Mfo = 'mfo';

    public function label(): string
    {
        return match ($this) {
            self::Kwh => 'kWh',
            self::Hsd => 'HSD',
            self::Mfo => 'MFO',
        };
    }
}
