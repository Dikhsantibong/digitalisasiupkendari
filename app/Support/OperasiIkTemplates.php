<?php

namespace App\Support;

/**
 * Starting templates of an Instruksi Kerja (IK) Operasi (same structure as
 * {@see HarIkTemplates}). The user picks one and edits everything; points use
 * `**tebal**` for bold words and may carry sub-points.
 */
class OperasiIkTemplates
{
    /**
     * @return list<array{key: string, nama: string, deskripsi: string, judul: string, mesin: string, sections: list<array<string, mixed>>}>
     */
    public static function all(): array
    {
        $apd = 'Pastikan memakai alat pelindung diri ( APD ) setiap melaksanakan kegiatan di dalam area lingkungan mesin PLTD';
        $alat = ['Helm', 'Tutup Telinga', 'Sepatu Safety', 'Sarung Tangan', 'Senter', 'Radio HT'];

        return [
            [
                'key' => 'kosong',
                'nama' => 'Kosong (format standar)',
                'deskripsi' => 'Alat, Pelaksana, Langkah Pelaksanaan — isi sendiri.',
                'judul' => "PENGOPERASIAN ...\nMESIN ...",
                'mesin' => '',
                'sections' => [
                    HarIkTemplates::section('ALAT', true, 'angka', ['']),
                    HarIkTemplates::section('PELAKSANA', true, 'paragraf', ['Operator Regu Shift PLTD']),
                    HarIkTemplates::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    HarIkTemplates::section('PERSIAPAN', false, 'angka', ['']),
                ],
            ],
            [
                'key' => 'start-mesin',
                'nama' => 'Start Mesin PLTD',
                'deskripsi' => 'Pemeriksaan sebelum start, start mesin, sinkron & pembebanan, pencatatan logsheet.',
                'judul' => "PENGOPERASIAN START MESIN\nPEMBANGKIT LISTRIK TENAGA DIESEL (PLTD)",
                'mesin' => '',
                'sections' => [
                    HarIkTemplates::section('ALAT', true, 'angka', $alat),
                    HarIkTemplates::section('PELAKSANA', true, 'paragraf', ['Operator Regu Shift PLTD']),
                    HarIkTemplates::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    HarIkTemplates::section('PEMERIKSAAN SEBELUM START', false, 'angka', [
                        'Pastikan tidak ada pekerjaan pemeliharaan pada mesin (**PTW** sudah ditutup)',
                        'Periksa level **oli pelumas** mesin pada dipstick (posisi normal)',
                        'Periksa level **air pendingin** pada expansion tank / radiator',
                        ['Periksa bahan bakar', ['Level daily tank cukup', 'Keran bahan bakar dalam posisi terbuka']],
                        'Periksa tegangan **baterai start** dan kondisi terminal',
                        'Periksa tidak ada kebocoran oli, air pendingin dan bahan bakar',
                        'Pastikan **CB generator** dalam posisi OFF (open)',
                    ], true),
                    HarIkTemplates::section('PROSEDUR START MESIN', false, 'panah', [
                        'Laporkan rencana start mesin kepada **Leader Shift / Dispatcher**',
                        'Pilih mode operasi pada panel kontrol (Manual / Auto)',
                        'Tekan tombol **START** dan amati putaran mesin hingga mencapai putaran nominal',
                        ['Periksa parameter mesin setelah running', ['Tekanan oli pelumas', 'Temperatur air pendingin', 'Tegangan & frekuensi generator']],
                        'Lakukan **sinkronisasi** lalu masukkan CB generator',
                        'Naikkan beban secara bertahap sesuai perintah dispatcher',
                        'Catat jam start dan parameter mesin pada **logsheet**',
                    ]),
                ],
            ],
            [
                'key' => 'stop-mesin',
                'nama' => 'Stop Mesin PLTD',
                'deskripsi' => 'Penurunan beban, lepas CB, cooling down, stop mesin, pemeriksaan & pencatatan.',
                'judul' => "PENGOPERASIAN STOP MESIN\nPEMBANGKIT LISTRIK TENAGA DIESEL (PLTD)",
                'mesin' => '',
                'sections' => [
                    HarIkTemplates::section('ALAT', true, 'angka', $alat),
                    HarIkTemplates::section('PELAKSANA', true, 'paragraf', ['Operator Regu Shift PLTD']),
                    HarIkTemplates::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    HarIkTemplates::section('PROSEDUR STOP MESIN', false, 'angka', [
                        'Laporkan rencana stop mesin kepada **Leader Shift / Dispatcher**',
                        'Turunkan beban secara bertahap hingga beban minimum',
                        'Lepas **CB generator** (posisi OFF / open)',
                        'Biarkan mesin berputar tanpa beban (**cooling down**) sesuai ketentuan pabrikan',
                        'Tekan tombol **STOP** dan pastikan mesin berhenti sempurna',
                    ], true),
                    HarIkTemplates::section('SETELAH MESIN STOP', false, 'panah', [
                        'Periksa tidak ada kebocoran oli, air pendingin dan bahan bakar',
                        'Kembalikan mode operasi sesuai status mesin (standby / pemeliharaan)',
                        'Catat jam stop, penyebab stop dan parameter terakhir pada **logsheet**',
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
