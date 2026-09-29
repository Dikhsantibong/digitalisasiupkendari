<?php

namespace App\Support;

/**
 * Starting templates of a Dokumen IK K3 (Instruksi Kerja): the user picks one
 * when creating an IK and edits the heading, points and document identity.
 * "kosong" is the blank structure of the official form (Alat, Bahan,
 * Referensi, Langkah Pelaksanaan).
 */
class K3IkTemplates
{
    /**
     * @return list<array{key: string, judul: string, no_dokumen: string, deskripsi: string, sections: list<array{judul: string, gaya: string, pengantar: string, butir: list<string>}>}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'kosong',
                'judul' => 'INSTRUKSI KERJA ...',
                'no_dokumen' => 'SMK3/IK/..',
                'deskripsi' => 'Format standar kosong: Alat, Bahan, Referensi, Langkah Pelaksanaan.',
                'sections' => [
                    self::section('ALAT', 'butir', '', ['']),
                    self::section('BAHAN', 'butir', '', ['']),
                    self::section('REFERENSI', 'butir', '', ['']),
                    self::section('LANGKAH PELAKSANAAN', 'huruf', '', ['']),
                ],
            ],
            [
                'key' => 'evakuasi',
                'judul' => 'INSTRUKSI KERJA PELAKSANAAN EVAKUASI',
                'no_dokumen' => 'SMK3/IK/06-11-05',
                'deskripsi' => 'Tindakan saat sirine/tanda keadaan darurat hingga evakuasi korban.',
                'sections' => [
                    self::section('ALAT', 'butir', '', ['Tandu', 'Kendaraan', 'Peluit', 'Sirine', 'Alat komunikasi', 'Tenda', 'Peralatan dapur umum']),
                    self::section('BAHAN', 'butir', '', ['Air', 'Makanan', 'Minuman']),
                    self::section('REFERENSI', 'butir', '', ['Prosedur Penanggulangan Keadaan Darurat']),
                    self::section('LANGKAH PELAKSANAAN', 'huruf', 'Bila terdengar bunyi sirine panjang atau kentongan sebagai tanda keadaan darurat atau diumumkan melalui panggilan paging/telepon maka segera dilakukan :', [
                        'Satpam mengamankan lokasi keadaan darurat',
                        'Tim yang telah ditunjuk sebagai pelaksana evakuasi segera mempersiapkan peralatan dan membawa ke tempat yang membutuhkan',
                        'Semua pegawai/karyawan segera meninggalkan tempat kerja dan menuju tempat evakuasi yang telah ditentukan',
                        'Petugas evakuasi segera mengamankan semua personil, barang berharga & dokumen penting',
                        'Sambil menunggu pemberitahuan selanjutnya semua pegawai/karyawan tidak diperkenankan meninggalkan tempat evakuasi',
                        'Jika terdapat korban maka dilakukan penanganan oleh Tim Medis dan untuk memperoleh penanganan lebih lanjut dibawa ke Rumah Sakit',
                    ]),
                ],
            ],
            [
                'key' => 'apar',
                'judul' => 'INSTRUKSI KERJA PENGGUNAAN ALAT PEMADAM API RINGAN (APAR)',
                'no_dokumen' => 'SMK3/IK/06-11-..',
                'deskripsi' => 'Cara memadamkan api awal dengan APAR (teknik PASS).',
                'sections' => [
                    self::section('ALAT', 'butir', '', ['APAR sesuai kelas kebakaran (Powder / CO2 / Foam)', 'Alat Pelindung Diri (sarung tangan, masker)']),
                    self::section('BAHAN', 'butir', '', ['-']),
                    self::section('REFERENSI', 'butir', '', ['Prosedur Penanggulangan Keadaan Darurat', 'Permenaker No. PER.04/MEN/1980 tentang APAR']),
                    self::section('LANGKAH PELAKSANAAN', 'huruf', 'Apabila terjadi kebakaran awal, lakukan langkah berikut :', [
                        'Beritahukan kejadian kebakaran kepada rekan kerja dan petugas K3',
                        'Ambil APAR terdekat yang sesuai dengan kelas kebakaran',
                        'Periksa tekanan pada indikator APAR (jarum pada area hijau)',
                        'Tarik pin pengaman (Pull)',
                        'Arahkan nozzle / selang ke pangkal api dari arah membelakangi angin (Aim)',
                        'Tekan tuas / handle APAR (Squeeze)',
                        'Sapukan semburan ke kiri dan ke kanan hingga api padam (Sweep)',
                        'Pastikan api benar-benar padam dan laporkan penggunaan APAR kepada petugas K3',
                    ]),
                ],
            ],
            [
                'key' => 'apd',
                'judul' => 'INSTRUKSI KERJA PENGGUNAAN ALAT PELINDUNG DIRI (APD)',
                'no_dokumen' => 'SMK3/IK/06-11-..',
                'deskripsi' => 'Pemilihan, pemeriksaan, pemakaian dan perawatan APD.',
                'sections' => [
                    self::section('ALAT', 'butir', '', ['Safety helmet', 'Safety shoes', 'Ear plug / ear muff', 'Sarung tangan', 'Kacamata pelindung', 'Masker', 'Wearpack']),
                    self::section('BAHAN', 'butir', '', ['-']),
                    self::section('REFERENSI', 'butir', '', ['Permenakertrans No. PER.08/MEN/VII/2010 tentang APD', 'Identifikasi Bahaya & Penilaian Risiko (IBPR)']),
                    self::section('LANGKAH PELAKSANAAN', 'huruf', '', [
                        'Identifikasi potensi bahaya di area kerja sebelum pekerjaan dimulai',
                        'Pilih APD yang sesuai dengan potensi bahaya pekerjaan',
                        'Periksa kondisi APD (tidak rusak, bersih dan ukuran sesuai)',
                        'Kenakan APD dengan benar sebelum memasuki area kerja',
                        'Gunakan APD selama pekerjaan berlangsung',
                        'Setelah selesai, bersihkan dan simpan APD pada tempatnya',
                        'Laporkan APD yang rusak kepada petugas K3 untuk diganti',
                    ]),
                ],
            ],
            [
                'key' => 'limbah-b3',
                'judul' => 'INSTRUKSI KERJA PENANGANAN LIMBAH B3',
                'no_dokumen' => 'SMK3/IK/06-11-..',
                'deskripsi' => 'Pengumpulan, pengemasan dan penyimpanan limbah B3 di TPS.',
                'sections' => [
                    self::section('ALAT', 'butir', '', ['Drum / wadah limbah B3 berlabel', 'Sarung tangan kimia', 'Masker', 'Kacamata pelindung', 'Spill kit']),
                    self::section('BAHAN', 'butir', '', ['Oli bekas', 'Majun terkontaminasi', 'Filter bekas', 'Aki bekas']),
                    self::section('REFERENSI', 'butir', '', ['PP No. 22 Tahun 2021 tentang Pengelolaan Lingkungan Hidup', 'Prosedur Pengelolaan Limbah B3']),
                    self::section('LANGKAH PELAKSANAAN', 'huruf', '', [
                        'Gunakan APD lengkap sebelum menangani limbah B3',
                        'Pisahkan limbah B3 sesuai jenisnya dan jangan dicampur',
                        'Masukkan limbah ke dalam wadah yang sesuai dan tertutup rapat',
                        'Beri label dan simbol limbah B3 pada wadah',
                        'Simpan wadah di TPS Limbah B3 dan catat pada logbook',
                        'Bersihkan tumpahan dengan spill kit apabila terjadi ceceran',
                        'Serahkan limbah kepada pihak ketiga berizin sesuai jadwal',
                    ]),
                ],
            ],
            [
                'key' => 'hydrant',
                'judul' => 'INSTRUKSI KERJA PENGOPERASIAN HYDRANT',
                'no_dokumen' => 'SMK3/IK/06-11-..',
                'deskripsi' => 'Penggunaan hydrant box untuk pemadaman kebakaran.',
                'sections' => [
                    self::section('ALAT', 'butir', '', ['Hydrant box', 'Selang hydrant (hose)', 'Nozzle', 'Kunci hydrant', 'APD pemadam']),
                    self::section('BAHAN', 'butir', '', ['Air']),
                    self::section('REFERENSI', 'butir', '', ['Prosedur Penanggulangan Keadaan Darurat', 'SNI 03-1745-2000 Sistem Pipa Tegak dan Slang']),
                    self::section('LANGKAH PELAKSANAAN', 'angka', '', [
                        'Buka pintu hydrant box',
                        'Gelar selang hydrant hingga lurus dan tidak tertekuk',
                        'Sambungkan selang ke kopling valve dan pasang nozzle',
                        'Pegang nozzle dengan kuat oleh minimal 2 orang',
                        'Buka valve hydrant perlahan hingga air mengalir',
                        'Arahkan semburan ke pangkal api',
                        'Setelah api padam, tutup valve, keringkan dan gulung kembali selang',
                    ]),
                ],
            ],
        ];
    }

    /**
     * @return array{key: string, judul: string, no_dokumen: string, deskripsi: string, sections: list<array{judul: string, gaya: string, pengantar: string, butir: list<string>}>}
     */
    public static function find(string $key): array
    {
        return collect(self::all())->firstWhere('key', $key) ?? self::all()[0];
    }

    /**
     * @param  list<string>  $butir
     * @return array{judul: string, gaya: string, pengantar: string, butir: list<string>}
     */
    private static function section(string $judul, string $gaya, string $pengantar, array $butir): array
    {
        return ['judul' => $judul, 'gaya' => $gaya, 'pengantar' => $pengantar, 'butir' => $butir];
    }
}
