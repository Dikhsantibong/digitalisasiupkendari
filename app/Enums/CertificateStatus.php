<?php

namespace App\Enums;

/**
 * The derived monitoring status of a certificate or extinguisher, computed from
 * its retest / expiry date against today (never stored). Drives the badge
 * colour in the K3 monitoring screen.
 */
enum CertificateStatus: string
{
    case Aktif = 'aktif';
    case Mendekati = 'mendekati';
    case Expired = 'expired';
    case Belum = 'belum';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Mendekati => 'Mendekati Expired',
            self::Expired => 'Expired',
            self::Belum => 'Belum Ada Data',
        };
    }

    /** Tone hint for the frontend badge. */
    public function tone(): string
    {
        return match ($this) {
            self::Aktif => 'success',
            self::Mendekati => 'warning',
            self::Expired => 'danger',
            self::Belum => 'neutral',
        };
    }
}
