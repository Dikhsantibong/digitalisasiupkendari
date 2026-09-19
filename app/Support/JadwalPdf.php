<?php

namespace App\Support;

/**
 * Small helpers shared by the OPERASI "Jadwal" PDF exports: base64-embedded
 * PLN & MKP logos for the letterhead, and Indonesian month names.
 */
class JadwalPdf
{
    /**
     * @return array{logoLeft: string|null, logoRight: string|null}
     */
    public static function logos(): array
    {
        $left = public_path('logo/sidebar-logo.png');
        $right = public_path('logo/mkp.jpg');

        return [
            'logoLeft' => file_exists($left) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($left)) : null,
            'logoRight' => file_exists($right) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($right)) : null,
        ];
    }

    public static function monthName(int $month): string
    {
        $names = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
            5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
            9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
        ];

        return $names[$month] ?? '';
    }
}
