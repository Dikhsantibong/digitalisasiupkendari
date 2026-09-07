<?php

namespace App\Enums;

/**
 * The operational bucket a Star-Stop status code falls into. Machine hours are
 * derived from the Star-Stop log and grouped by this category — never typed in.
 */
enum StatusCodeCategory: string
{
    case Operasi = 'operasi';
    case Har = 'har';
    case Gangguan = 'gangguan';
    case Standby = 'standby';

    public function label(): string
    {
        return match ($this) {
            self::Operasi => 'Operasi',
            self::Har => 'Pemeliharaan (HAR)',
            self::Gangguan => 'Gangguan',
            self::Standby => 'Standby',
        };
    }
}
