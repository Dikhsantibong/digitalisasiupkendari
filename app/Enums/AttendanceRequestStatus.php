<?php

namespace App\Enums;

/**
 * Where a Pengajuan Absensi stands: a tukar shift first waits for the
 * pengganti, then every request waits for the approver (Koordinator Operasi /
 * Project Leader — operator.absensi.write), who approves (the Jadwal Shift is
 * updated) or rejects it. The employee may cancel while it is pending.
 */
enum AttendanceRequestStatus: string
{
    case MenungguPengganti = 'menunggu_pengganti';
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::MenungguPengganti => 'Menunggu Konfirmasi Pengganti',
            self::MenungguPersetujuan => 'Menunggu Persetujuan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /** StatusBadge tone. */
    public function tone(): string
    {
        return match ($this) {
            self::MenungguPengganti, self::MenungguPersetujuan => 'warning',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
            self::Dibatalkan => 'neutral',
        };
    }

    public function isPending(): bool
    {
        return $this === self::MenungguPengganti || $this === self::MenungguPersetujuan;
    }
}
