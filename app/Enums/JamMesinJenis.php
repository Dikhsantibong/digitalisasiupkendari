<?php

namespace App\Enums;

/**
 * The machine-hour sheets of Pengusahaan Operasi that are typed in. Jam Siap
 * Operasi is not one of them: it is 24 jam minus these three, per day.
 */
enum JamMesinJenis: string
{
    case Operasi = 'operasi';
    case Pemeliharaan = 'pemeliharaan';
    case Gangguan = 'gangguan';

    public function label(): string
    {
        return match ($this) {
            self::Operasi => 'Jam Operasi',
            self::Pemeliharaan => 'Jam Pemeliharaan',
            self::Gangguan => 'Jam Gangguan',
        };
    }

    /** The heading of the printed sheet. */
    public function title(): string
    {
        return match ($this) {
            self::Operasi => 'JAM OPERASI MESIN PEMBANGKIT',
            self::Pemeliharaan => 'JAM PEMELIHARAAN (PO) PEMBANGKIT',
            self::Gangguan => 'JAM GANGGUAN PEMBANGKIT',
        };
    }

    /** The Star-Stop status category the hours can be taken from. */
    public function statusCategory(): StatusCodeCategory
    {
        return match ($this) {
            self::Operasi => StatusCodeCategory::Operasi,
            self::Pemeliharaan => StatusCodeCategory::Har,
            self::Gangguan => StatusCodeCategory::Gangguan,
        };
    }
}
