<?php

namespace App\Enums;

/**
 * Whether a maintenance schedule matrix holds the plan or the realisation.
 */
enum SchedulePlanType: string
{
    case Rencana = 'rencana';
    case Realisasi = 'realisasi';

    public function label(): string
    {
        return match ($this) {
            self::Rencana => 'Rencana',
            self::Realisasi => 'Realisasi',
        };
    }
}
