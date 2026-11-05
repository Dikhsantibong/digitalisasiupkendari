<?php

namespace App\Enums;

/**
 * The three daily opname sheets of the Operator module.
 */
enum OpnameJenis: string
{
    case Bbm = 'bbm';
    case Pelumas = 'pelumas';
    case Kwh = 'kwh';

    public function label(): string
    {
        return match ($this) {
            self::Bbm => 'Opname BBM',
            self::Pelumas => 'Opname Pelumas',
            self::Kwh => 'Opname kWh',
        };
    }
}
