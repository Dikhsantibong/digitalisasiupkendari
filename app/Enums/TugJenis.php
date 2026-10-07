<?php

namespace App\Enums;

/**
 * The kind of TUG 9 (Rekap Bon Pemakaian Energi Primer) a header belongs to.
 */
enum TugJenis: string
{
    case Pelumas = 'pelumas';
    case Bbm = 'bbm';

    public function label(): string
    {
        return match ($this) {
            self::Pelumas => 'Pelumas',
            self::Bbm => 'BBM',
        };
    }
}
