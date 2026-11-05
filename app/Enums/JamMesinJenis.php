<?php

namespace App\Enums;

/**
 * The machine-hour sheets of Pengusahaan Operasi that are typed in (the Jam
 * Pembangkit menu): per mesin per day Operasi + PO + MO + Gangguan + Standby
 * make 24 jam. Jam Siap Operasi is not one of them: it is 24 jam minus the
 * outage-or-running hours ({@see self::countsAgainstSiap()}), i.e. the standby.
 */
enum JamMesinJenis: string
{
    case Operasi = 'operasi';
    case Pemeliharaan = 'pemeliharaan';
    case MaintenanceOutage = 'mo';
    case Gangguan = 'gangguan';
    case Standby = 'standby';

    public function label(): string
    {
        return match ($this) {
            self::Operasi => 'Jam Operasi',
            self::Pemeliharaan => 'Jam Pemeliharaan',
            self::MaintenanceOutage => 'Jam MO',
            self::Gangguan => 'Jam Gangguan',
            self::Standby => 'Jam Standby',
        };
    }

    /** The heading of the printed sheet. */
    public function title(): string
    {
        return match ($this) {
            self::Operasi => 'JAM OPERASI MESIN PEMBANGKIT',
            self::Pemeliharaan => 'JAM PEMELIHARAAN (PO) PEMBANGKIT',
            self::MaintenanceOutage => 'JAM MAINTENANCE OUTAGE (MO) PEMBANGKIT',
            self::Gangguan => 'JAM GANGGUAN PEMBANGKIT',
            self::Standby => 'JAM STANDBY PEMBANGKIT',
        };
    }

    /** The Star-Stop status category the hours can be taken from; MO has none. */
    public function statusCategory(): ?StatusCodeCategory
    {
        return match ($this) {
            self::Operasi => StatusCodeCategory::Operasi,
            self::Pemeliharaan => StatusCodeCategory::Har,
            self::MaintenanceOutage => null,
            self::Gangguan => StatusCodeCategory::Gangguan,
            self::Standby => StatusCodeCategory::Standby,
        };
    }

    /** Whether the hours leave the mesin not ready (Jam Siap Operasi = 24 minus these). */
    public function countsAgainstSiap(): bool
    {
        return $this !== self::Standby;
    }
}
