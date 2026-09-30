<?php

namespace App\Support;

/**
 * Starting templates of an Instruksi Kerja (IK) Pemeliharaan. The user picks
 * one and edits everything. Points use `**tebal**` for bold words and may
 * carry sub-points (shown with a dash under their parent point).
 */
class HarIkTemplates
{
    /**
     * @return list<array{key: string, nama: string, deskripsi: string, judul: string, mesin: string, sections: list<array{judul: string, bernomor: bool, gaya: string, lanjut: bool, pengantar: string, butir: list<array{teks: string, sub: list<string>}>}>}>
     */
    public static function all(): array
    {
        $apd = 'Pastikan Memakai alat pelindung diri ( APD ) setiap melaksanakan kegiatan di dalam area lingkungan mesin PLTD';

        return [
            [
                'key' => 'kosong',
                'nama' => 'Kosong (format standar)',
                'deskripsi' => 'Alat, Pelaksana, Langkah Pelaksanaan — isi sendiri.',
                'judul' => "PEKERJAAN ...\nMESIN ...",
                'mesin' => '',
                'sections' => [
                    self::section('ALAT', true, 'angka', ['']),
                    self::section('PELAKSANA', true, 'paragraf', ['Regu Pemeliharaan PLNT SITE PLTD']),
                    self::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    self::section('PERSIAPAN AWAL SEBELUM PEMELIHARAAN', false, 'angka', ['']),
                ],
            ],
            [
                'key' => 'pm-1500-cummins',
                'nama' => 'PM 1500 Jam — Mesin Cummins KTA 50',
                'deskripsi' => 'Preventive maintenance 1500 jam: filter, oli, belt, coolant, baterai, adjust valve & injector.',
                'judul' => "PEKERJAAN PREVENTIVE MAINTENANCE PM 1500 JAM\nMESIN CUMMINS KTA 50",
                'mesin' => 'Cummins KTA 50',
                'sections' => [
                    self::section('ALAT', true, 'angka', ['Helm', 'Tutup Telinga', 'Sepatu Safety', 'Tools standart', 'Spesial tool adjust injector', 'Spesial tools adjust valve']),
                    self::section('PELAKSANA', true, 'paragraf', ['Regu Pemeliharaan PLNT SITE PLTD']),
                    self::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    self::section('PERSIAPAN AWAL SEBELUM PEMELIHARAAN', false, 'angka', [
                        'Sudah mendapatkan ijin keluar sistem dari PLTD/ P2B, sesuai dengan dokumen yang kita kirim ( safety permit, JSA, Scedule )',
                        'Ambil data before maintenance ( data parameter mesin )',
                        'Stop enggine',
                        'Buka filter Udara ( conditoinal, kalau perlu di ganti baru )',
                        'Kuras oli ( Minimal 15 menit enggine stop baru bisa kuras oli )',
                        'Buka Filter Oli ( tampung buangan oli dengan ember ), ganti dengan yang baru tapi sebelumnya isi filter dengan oli baru melalui lubang inlet filter',
                        ['Buka Filter bahan bakar', [
                            'Tutup keran bahan bakar yang masuk maupun yang keluar',
                            'Buka filter bahan bakar',
                            'Ganti dengan Filter baru',
                            'Sebelum dipasang isi filter bahan bakar dengan bahan bakar melalui lubang yg ada di samping',
                            'Pasang Filter bahan bakar dan kencangkan',
                            'Buka hose saluran bahan bakar yang keluar dari filter',
                            'Buka keran saluran bahan bakar masuk, perhatikan udara yang keluar dari selang apabila udara sudah habis pasang kembali',
                        ]],
                        'Buka filter by pass oli ( tampung ceceran oli dengan ember ), sebelum memasangnya kembali isi filter dengan oli baru melalui lubang inlet filter',
                        'Check kekencangan altenator belt ( Check fisiknya apakah ada keretakan )',
                        'Check kekencangan fan belt ( check fisik nya apakah ada keretakan )',
                        'Check kebocoran coolant',
                        'Tambah level coolant',
                        'Check baterai ( tambah air baterai )',
                        'Check kekencangan baut pengikat terminal baterai',
                        'Check kekencangan baut klam pipa ekhaust ( turbo )',
                        'Check kekencangan baut klam udara masuk',
                        'Check kekencangan baut pondasi mesin',
                        'Check kebersihan kisi kisi radiator ( bersihkan )',
                        'Check kekencangan baut baut pengikat terminal kabel dalam panel GCV',
                        'Check fuse',
                        'Check Relay',
                        'Check Kw meter',
                        'Adjust Valve dan Adjust injector',
                    ]),
                    self::section('PERSIAPAN SEBELUM ADJUST VALVE DAN INJECTOR', false, 'angka', [
                        'kondisi engine dalam keadaan dingin',
                        'persiapkan semua tools seperti : torque meter, momen, obeng ( - ), fuller gauge, kunci ring 3/4 dan 7/8 kunci sock dan recet dan mata sock ukuran 9/16 dan tipex',
                    ]),
                    self::section('PROSEDUR PEKERJAAN', false, 'panah', [
                        'lepaskan baut cover valve 9/16',
                        'lepas sped pinion gear',
                        'lepas cover pinion gear yang huruf C',
                        'putar flywheel sampai mendapatkan TOP pada DAMPER dan TOP flywheel',
                        'adjust valve IN dan EX terlebih dahulu dimana IN= 0,14mm dan EX= 0,27mm jangan lupa beri tanda tipex jika adjustnya sudah selesai sehingga mudah dibedakan yang sudah adjust dan belum, dan moment screw rock arm 45 LB-FT',
                        'adjust injector menggunakan torque meter sesuai panduan name plate yang torsinya 90 inch LBS beri tanda ceklist menggunakan tipex jika selesai.',
                        'setelah adjust injector putar lagi flywheel hingga dapat damper yang berikutnya dan adjust valve dan injector mengikuti TOP pada damper dan sekali kali cek top pada flywheel.',
                        'Setelah selesai semua adjust valve dan injector jangan lupa bersihkan pinggiran atas cylinder head dan cover valvenya',
                        'Pastikan tidak ada benda yang masuk atau tertinggal dalam cylinder head gunakan senter jika perlu',
                        'Pasang kembali semua cover valve dan kencangkan bautnya',
                        'Pasang kembali lock pinion gear flywheel',
                        'Setelah pekerjaan selesai pastikan tools tidak ada yang tertinggal',
                        'Lakukan pengecekan pada lubricating sistem, fuel sistem, air sistem, coolant sistem, elektrical sistem.',
                        'Lakukan test running pada engine semaksimal 15 menit dan dengarkan suara valve dan monitoring asap muffler.',
                    ]),
                    self::section('PEKERJAAN FINISHING ( Lihat SOP setelah pemeliharaan )', false, 'angka', []),
                ],
            ],
            [
                'key' => 'setelah-pm-cummins',
                'nama' => 'Setelah Preventive Maintenance — Mesin Cummins KTA 50',
                'deskripsi' => 'Persiapan start, setting running idle panel PCC 3300 dan pembersihan setelah PM.',
                'judul' => "SETELAH MELAKUKAN PEKERJAAN PREVENTIVE MAINTENANCE\nMESIN CUMMINS KTA 50",
                'mesin' => 'Cummins KTA 50',
                'sections' => [
                    self::section('ALAT', true, 'angka', ['Helm', 'Tutup Telinga', 'Sepatu Safety']),
                    self::section('PELAKSANA', true, 'paragraf', ['Regu Pemeliharaan PLNT SITE PLTD']),
                    self::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    self::section('PERSIAPAN AWAL SEBELUM START MESIN.', false, 'angka', [
                        'Periksa Kekencangan Kabel batere',
                        'Pastikan Tegangan Dc batere Masih Memadai ( >24vdc )',
                        'Periksa Level Oli',
                        'Cek Level air Radiator',
                        'Cek sekitar mesin bahwa tidak ada orang Lain yg ada di dekat mesin',
                        'Cek Keberadaan APAR',
                        'PASTIKAN SETTING RUNNING ENGGINE PADA POSISI IDLE ( 800 RPM )',
                    ]),
                    self::section('SETTING RUNNING POSISI IDLE PADA PANEL PCC 3300', false, 'angka', [
                        'Masuk menu **HISTORY/ ABOUT**',
                        'Tekan tanda CURSOR kebawah ▼ di sisi kanan bawah panel PCC',
                        'Pilih Menu **ADJUST** kemudian tekan **OK**',
                        'Pilih Menu **RATE/ IDLE** kemudian tekan **OK**',
                        'Pilih posisi **IDLE**',
                        'Start Enggine ( tekan tombol **STOP** kemudian **RESET**, selanjutnya tekan tombol **MANUAL** dan **START** enggine )',
                        'Enggine akan running dengan RPM 800 KW',
                        'Check Keliling enggine, pastikan tidak ada kebocoran ( sistem pelumasan, sistem bahan bakar, dan sistem pendinginan )',
                        'Biarkan unit Running selama 2 menit kemudian pada panel PCC ubah menu pada posisi **RATE**, enggine akan running ke 1500 RPM kemudian tekan tombol **OK**',
                        'Apabila semua kondisi dalam keadaan aman enggine siap di serahkan kembali ke regu operator',
                    ], lanjut: true),
                    self::section('PEMBERSIHAN SETELAH MELAKUKAN PREVENTIVE MAINTENANCE', false, 'angka', [
                        'Kumpulkan alat kerja, bersihkan kemudian simpan',
                        'Bersihkan lingkungan di sekitar enggine',
                        'Kumpulkan limbah B3 Padat ( kain majun, filter bekas ) simpan dalam TPS dan tempatkan penampungan limbah padat pada tempatnya',
                        'Kumpulkan limbah B3 cair dan tampung dalam TPS',
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

    /**
     * One IK part; also used by the other modules' IK templates (e.g. {@see OperasiIkTemplates}).
     *
     * @param  list<string|array{0: string, 1: list<string>}>  $points  a point, or [point, sub-points]
     * @return array{judul: string, bernomor: bool, gaya: string, lanjut: bool, pengantar: string, butir: list<array{teks: string, sub: list<string>}>}
     */
    public static function section(string $judul, bool $bernomor, string $gaya, array $points, bool $lanjut = false, string $pengantar = ''): array
    {
        return [
            'judul' => $judul,
            'bernomor' => $bernomor,
            'gaya' => $gaya,
            'lanjut' => $lanjut,
            'pengantar' => $pengantar,
            'butir' => array_map(
                fn ($point): array => is_array($point) ? ['teks' => $point[0], 'sub' => $point[1]] : ['teks' => $point, 'sub' => []],
                $points,
            ),
        ];
    }
}
