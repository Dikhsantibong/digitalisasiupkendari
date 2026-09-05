<?php

namespace App\Enums;

/**
 * The kind of generating technology a unit uses.
 */
enum UnitType: string
{
    case Pltu = 'pltu';
    case Pltd = 'pltd';
    case Pltg = 'pltg';
    case Pltm = 'pltm';
    case Pltmg = 'pltmg';

    public function label(): string
    {
        return match ($this) {
            self::Pltu => 'PLTU',
            self::Pltd => 'PLTD',
            self::Pltg => 'PLTG',
            self::Pltm => 'PLTM',
            self::Pltmg => 'PLTMG',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Pltu => 'Pembangkit Listrik Tenaga Uap',
            self::Pltd => 'Pembangkit Listrik Tenaga Diesel',
            self::Pltg => 'Pembangkit Listrik Tenaga Gas',
            self::Pltm => 'Pembangkit Listrik Tenaga Minihidro',
            self::Pltmg => 'Pembangkit Listrik Tenaga Mesin Gas',
        };
    }
}
