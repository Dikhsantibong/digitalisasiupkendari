<?php

namespace App\Enums;

/**
 * The fuel held by a storage tank. Narrower than {@see FuelType}: a tank stores
 * exactly one product.
 */
enum TankFuelType: string
{
    case Hsd = 'hsd';
    case Mfo = 'mfo';

    public function label(): string
    {
        return match ($this) {
            self::Hsd => 'HSD',
            self::Mfo => 'MFO',
        };
    }
}
