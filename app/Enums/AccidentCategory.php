<?php

namespace App\Enums;

/**
 * The category of a K3 accident/occupational-disease report (PAK/PAHK and where
 * it occurred).
 */
enum AccidentCategory: string
{
    case Pak = 'pak';
    case Pahk = 'pahk';
    case Instalasi = 'instalasi';
    case Masyarakat = 'masyarakat';

    public function label(): string
    {
        return match ($this) {
            self::Pak => 'PAK (Penyakit Akibat Kerja)',
            self::Pahk => 'PAHK (Penyakit Akibat Hubungan Kerja)',
            self::Instalasi => 'Kecelakaan Instalasi',
            self::Masyarakat => 'Kecelakaan Masyarakat',
        };
    }
}
