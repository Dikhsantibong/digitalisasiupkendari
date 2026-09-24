<?php

namespace App\Support;

/**
 * Template defaults and options for Formulir Metode Pengujian Peralatan K3.
 * Matches the official PLN NP / MKP Formulir Metode Pengujian Peralatan sheet.
 */
final class K3MetodePengujianPeralatanForm
{
    public const HASIL_UJI = [
        '-',
        'Memenuhi',
        'Tidak Memenuhi',
        'N/A',
    ];

    /**
     * Builds default template rows matching the official template.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultRows(): array
    {
        return [
            [
                'id' => null,
                'no_urut' => '1',
                'nama_peralatan' => 'Crane',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => '-',
                'uji_visual' => '-',
                'uji_fungsi' => '-',
                'uji_beban' => '-',
                'uji_hydro' => '-',
                'ndt' => '-',
                'uji_ultrasonic_thickness' => '-',
                'uji_ketahanan' => '-',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => 'Tidak ada crane',
                'sort_order' => 0,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_peralatan' => 'Tangki Timbun',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => 'Tangki 25 KL Containerized',
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => '-',
                'uji_beban' => 'Memenuhi',
                'uji_hydro' => '-',
                'ndt' => 'Memenuhi',
                'uji_ultrasonic_thickness' => '-',
                'uji_ketahanan' => '-',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => '',
                'sort_order' => 1,
            ],
            [
                'id' => null,
                'no_urut' => '3',
                'nama_peralatan' => 'Instalasi Penyalur Petir',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => '-',
                'uji_visual' => '-',
                'uji_fungsi' => '-',
                'uji_beban' => '-',
                'uji_hydro' => '-',
                'ndt' => '-',
                'uji_ultrasonic_thickness' => '-',
                'uji_ketahanan' => '-',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => 'Tidak ada instalasi penyalur petir',
                'sort_order' => 2,
            ],
            [
                'id' => null,
                'no_urut' => '4',
                'nama_peralatan' => 'Bejana Tekan',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => 'Air Receiver Tank',
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => 'Memenuhi',
                'uji_beban' => '-',
                'uji_hydro' => 'Memenuhi',
                'ndt' => '-',
                'uji_ultrasonic_thickness' => 'Memenuhi',
                'uji_ketahanan' => '-',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => '',
                'sort_order' => 3,
            ],
        ];
    }
}
