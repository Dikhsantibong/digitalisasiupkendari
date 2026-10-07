<?php

namespace App\Enums;

/**
 * The unit of measure a lubricant type is counted in. Kg marks a grease: the
 * Rekap Pelumas totals it apart from the pelumas (liter).
 */
enum LubricantUnit: string
{
    case Drum = 'drum';
    case Liter = 'liter';
    case Kg = 'kg';

    public function label(): string
    {
        return match ($this) {
            self::Drum => 'Drum',
            self::Liter => 'Liter',
            self::Kg => 'Kg (Grease)',
        };
    }
}
