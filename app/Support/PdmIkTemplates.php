<?php

namespace App\Support;

/**
 * Starting templates of an Instruksi Kerja (IK) PdM & Maturity Level (same
 * structure as {@see HarIkTemplates}). The user picks one and edits
 * everything; points use `**tebal**` for bold words and may carry sub-points.
 */
class PdmIkTemplates
{
    /**
     * @return list<array{key: string, nama: string, deskripsi: string, judul: string, mesin: string, sections: list<array<string, mixed>>}>
     */
    public static function all(): array
    {
        $apd = 'Pastikan memakai alat pelindung diri ( APD ) setiap melaksanakan kegiatan di dalam area lingkungan mesin PLTD';
        $pelaksana = ['Koordinator PdM & PIC PdM'];

        return [
            [
                'key' => 'kosong',
                'nama' => 'Kosong (format standar)',
                'deskripsi' => 'Alat, Pelaksana, Langkah Pelaksanaan — isi sendiri.',
                'judul' => "PELAKSANAAN ...\nPREDICTIVE MAINTENANCE (PdM)",
                'mesin' => '',
                'sections' => [
                    HarIkTemplates::section('ALAT', true, 'angka', ['']),
                    HarIkTemplates::section('PELAKSANA', true, 'paragraf', $pelaksana),
                    HarIkTemplates::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    HarIkTemplates::section('PERSIAPAN', false, 'angka', ['']),
                ],
            ],
            [
                'key' => 'vibrasi',
                'nama' => 'Pengukuran Vibrasi Mesin & Generator',
                'deskripsi' => 'Persiapan alat ukur, titik ukur, pengukuran saat mesin berbeban dan pencatatan hasil.',
                'judul' => "PENGUKURAN VIBRASI\nMESIN & GENERATOR PLTD",
                'mesin' => 'Mesin & Generator',
                'sections' => [
                    HarIkTemplates::section('ALAT', true, 'angka', ['Helm', 'Tutup Telinga', 'Sepatu Safety', 'Sarung Tangan', '**Vibration meter** beserta sensor', 'Formulir pengukuran vibrasi']),
                    HarIkTemplates::section('PELAKSANA', true, 'paragraf', $pelaksana),
                    HarIkTemplates::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    HarIkTemplates::section('PERSIAPAN', false, 'angka', [
                        'Koordinasikan jadwal pengukuran dengan **Leader Shift**',
                        'Pastikan baterai dan kalibrasi vibration meter dalam kondisi baik',
                        'Pastikan mesin beroperasi dengan beban stabil',
                    ], true),
                    HarIkTemplates::section('PENGUKURAN', false, 'panah', [
                        ['Ukur vibrasi pada setiap titik ukur', ['Bearing generator DE & NDE', 'Engine sisi kiri & kanan', 'Arah horizontal, vertikal dan aksial']],
                        'Catat nilai vibrasi (mm/s) pada formulir pengukuran',
                        'Bandingkan hasil dengan **standar ISO 10816** dan hasil pengukuran sebelumnya',
                    ]),
                    HarIkTemplates::section('PELAPORAN', false, 'angka', [
                        'Input hasil pengukuran pada aplikasi (menu Laporan Pengukuran Vibrasi)',
                        'Laporkan nilai yang melebihi batas kepada Team Leader Pemeliharaan untuk tindak lanjut',
                    ]),
                ],
            ],
            [
                'key' => 'sampel-pelumas',
                'nama' => 'Pengambilan Sampel Pelumas',
                'deskripsi' => 'Pengambilan sampel oli mesin untuk uji laboratorium, pelabelan dan pengiriman.',
                'judul' => "PENGAMBILAN SAMPEL PELUMAS\nMESIN PLTD",
                'mesin' => 'Mesin PLTD',
                'sections' => [
                    HarIkTemplates::section('ALAT', true, 'angka', ['Helm', 'Sepatu Safety', 'Sarung Tangan', 'Botol sampel bersih & label', 'Pompa / selang sampel', 'Majun']),
                    HarIkTemplates::section('PELAKSANA', true, 'paragraf', $pelaksana),
                    HarIkTemplates::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    HarIkTemplates::section('PENGAMBILAN SAMPEL', false, 'angka', [
                        'Ambil sampel saat mesin beroperasi / maksimal **30 menit** setelah mesin stop',
                        'Buang aliran awal pelumas sebelum mengisi botol sampel',
                        'Isi botol sampel hingga ± 3/4 volume lalu tutup rapat',
                    ], true),
                    HarIkTemplates::section('PELABELAN & PENGIRIMAN', false, 'panah', [
                        ['Isi label sampel', ['Unit & nomor mesin', 'Jam operasi mesin dan jam pakai pelumas', 'Tanggal pengambilan']],
                        'Catat pada form **Monitoring Pemeriksaan & Pengiriman Sample PdM**',
                        'Kirim sampel ke laboratorium sesuai jadwal',
                    ]),
                ],
            ],
        ];
    }

    /**
     * @return array{key: string, nama: string, deskripsi: string, judul: string, mesin: string, sections: list<array<string, mixed>>}
     */
    public static function find(string $key): array
    {
        return collect(self::all())->firstWhere('key', $key) ?? self::all()[0];
    }
}
