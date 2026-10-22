<?php

namespace App\Enums;

/**
 * What an employee asks for on the Pengajuan Absensi page. Izin, sakit and
 * cuti put the matching absence code (I, SKT, C) on the working days of the
 * range once approved; tukar shift hands the employee's shift on a date to a
 * colleague (pengganti), who confirms before the approver decides.
 */
enum AttendanceRequestType: string
{
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';
    case TukarShift = 'tukar_shift';

    public function label(): string
    {
        return match ($this) {
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Cuti => 'Cuti',
            self::TukarShift => 'Tukar Shift',
        };
    }

    /** The attendance code an approved request puts on the schedule (none for tukar shift: it moves shifts). */
    public function attendanceCode(): ?string
    {
        return match ($this) {
            self::Izin => 'I',
            self::Sakit => 'SKT',
            self::Cuti => 'C',
            self::TukarShift => null,
        };
    }

    public function needsSubstitute(): bool
    {
        return $this === self::TukarShift;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}
