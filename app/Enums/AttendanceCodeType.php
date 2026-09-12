<?php

namespace App\Enums;

/**
 * Whether an attendance code marks a worked shift (Pagi/Sore/Malam) or an
 * absence (cuti/sakit/izin/alpha/libur). Keeps the ambiguous Excel "S" (Sore,
 * a shift) apart from "SAKIT" (an absence, code SKT) in one place.
 */
enum AttendanceCodeType: string
{
    case Shift = 'shift';
    case Absence = 'absence';

    public function label(): string
    {
        return match ($this) {
            self::Shift => 'Shift',
            self::Absence => 'Ketidakhadiran',
        };
    }
}
