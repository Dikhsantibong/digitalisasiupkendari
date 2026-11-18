<?php

namespace App\Support;

/**
 * Starting templates of an Instruksi Kerja (IK) Logistik & Gudang (same
 * structure as {@see HarIkTemplates}). The user picks one and edits
 * everything; points use `**tebal**` for bold words and may carry sub-points.
 */
class LogistikIkTemplates
{
    /**
     * @return list<array{key: string, nama: string, deskripsi: string, judul: string, mesin: string, sections: list<array<string, mixed>>}>
     */
    public static function all(): array
    {
        $apd = 'Pastikan memakai alat pelindung diri ( APD ) setiap melaksanakan kegiatan di dalam area gudang pembangkit';
        $alat = ['Helm', 'Sepatu Safety', 'Sarung Tangan', 'Alat tulis & formulir', 'Kamera / HP untuk eviden'];
        $pelaksana = ['Koordinator Logistik & Office Logistik'];

        return [
            [
                'key' => 'kosong',
                'nama' => 'Kosong (format standar)',
                'deskripsi' => 'Alat, Pelaksana, Langkah Pelaksanaan — isi sendiri.',
                'judul' => "PELAKSANAAN ...\nLOGISTIK & GUDANG PEMBANGKIT",
                'mesin' => '',
                'sections' => [
                    HarIkTemplates::section('ALAT', true, 'angka', ['']),
                    HarIkTemplates::section('PELAKSANA', true, 'paragraf', $pelaksana),
                    HarIkTemplates::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    HarIkTemplates::section('PERSIAPAN', false, 'angka', ['']),
                ],
            ],
            [
                'key' => 'penerimaan-material',
                'nama' => 'Penerimaan Material di Gudang',
                'deskripsi' => 'Pemeriksaan dokumen & fisik material masuk, pencatatan, dan penyimpanan.',
                'judul' => "PENERIMAAN MATERIAL\nGUDANG PEMBANGKIT",
                'mesin' => 'Gudang',
                'sections' => [
                    HarIkTemplates::section('ALAT', true, 'angka', $alat),
                    HarIkTemplates::section('PELAKSANA', true, 'paragraf', $pelaksana),
                    HarIkTemplates::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    HarIkTemplates::section('PEMERIKSAAN DOKUMEN', false, 'angka', [
                        'Terima **surat jalan / delivery order** dari pengirim',
                        'Cocokkan nama material, spesifikasi dan jumlah dengan dokumen pemesanan',
                    ], true),
                    HarIkTemplates::section('PEMERIKSAAN FISIK', false, 'panah', [
                        'Periksa kondisi kemasan dan fisik material (tidak rusak / cacat)',
                        ['Hitung jumlah material', ['Sesuai dokumen: lanjut penerimaan', 'Tidak sesuai: buat **berita acara** selisih']],
                        'Dokumentasikan material yang diterima (foto eviden)',
                    ]),
                    HarIkTemplates::section('PENCATATAN & PENYIMPANAN', false, 'angka', [
                        'Input penerimaan material pada **aplikasi** dan kartu stok',
                        'Simpan material pada rak / lokasi sesuai kode penyimpanan',
                        'Tanda tangani dan arsipkan dokumen penerimaan',
                    ]),
                ],
            ],
            [
                'key' => 'stok-opname',
                'nama' => 'Stok Opname Gudang',
                'deskripsi' => 'Persiapan, penghitungan fisik, rekonsiliasi dengan sistem dan berita acara.',
                'judul' => "STOK OPNAME MATERIAL\nGUDANG PEMBANGKIT",
                'mesin' => 'Gudang',
                'sections' => [
                    HarIkTemplates::section('ALAT', true, 'angka', $alat),
                    HarIkTemplates::section('PELAKSANA', true, 'paragraf', $pelaksana),
                    HarIkTemplates::section('LANGKAH PELAKSANAAN', true, 'angka', [$apd]),
                    HarIkTemplates::section('PERSIAPAN', false, 'angka', [
                        'Cetak daftar stok dari **aplikasi** per tanggal opname',
                        'Hentikan sementara transaksi keluar / masuk material selama opname',
                    ], true),
                    HarIkTemplates::section('PENGHITUNGAN FISIK', false, 'panah', [
                        'Hitung fisik material per rak / lokasi penyimpanan',
                        'Catat hasil hitung pada lembar opname',
                        ['Bandingkan dengan saldo sistem', ['Sama: beri tanda sesuai', 'Selisih: telusuri transaksi terakhir']],
                    ]),
                    HarIkTemplates::section('PENYELESAIAN', false, 'angka', [
                        'Buat **berita acara stok opname** beserta daftar selisih',
                        'Lakukan penyesuaian saldo pada aplikasi setelah disetujui',
                        'Laporkan hasil opname kepada Team Leader Pemeliharaan',
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
