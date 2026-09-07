<?php

namespace App\Enums;

/**
 * The unit of measure a lubricant type is counted in.
 */
enum LubricantUnit: string
{
    case Drum = 'drum';
    case Liter = 'liter';

    public function label(): string
    {
        return match ($this) {
            self::Drum => 'Drum',
            self::Liter => 'Liter',
        };
    }
}
