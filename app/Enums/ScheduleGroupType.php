<?php

namespace App\Enums;

/**
 * The employee group a monthly work schedule covers: rotating shift workers
 * (Pagi/Sore/Malam/OFF) or non-shift workers on regular office hours.
 */
enum ScheduleGroupType: string
{
    case Shift = 'shift';
    case NonShift = 'non_shift';

    public function label(): string
    {
        return match ($this) {
            self::Shift => 'Kerja Shift',
            self::NonShift => 'Non-Shift',
        };
    }
}
