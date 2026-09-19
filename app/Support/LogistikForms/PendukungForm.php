<?php

namespace App\Support\LogistikForms;

/**
 * Laporan Pendukung: the supporting documents of the Laporan Logistik &
 * Gudang (uraian, folder, detail, keterangan). Portrait, like the form.
 */
class PendukungForm extends LogistikForm
{
    /** @var list<string> */
    public const URAIAN = [
        '1.LAPORAN LOGISTIK & GUDANG',
        '1.1 DATA RESUME',
        '1.2 DATA STOK OPNAME MATERIAL NORMAL',
        '1.3 DATA STOK OPNAME MATERIAL DEAD STOCK',
        '1.4 DATA PENERIMAAN MATERIAL',
        '1.5 DATA PEMAKAIAN MATERIAL',
        '1.6 DATA PEMAKAIAN PELUMAS',
        '1.7 RETURN',
        '1.8 PENGIRIMAN',
        '1.9 DATA INSTRUKSI KERJA',
        '2.LAPORAN KONDISI STOK DAN MATERIAL',
        '2.1 REKAPITULASI KONDISI STOK MATERIAL',
        '2.2 DAFTAR KONDISI STOK MATERIAL',
        '3.REKAPITULASI LOGBOOK MUTASI HARIAN',
        '4.LAPORAN MATURITY LEVEL BAGIAN LOGISTIK & GUDANG PEMBANGKIT',
    ];

    public function key(): string
    {
        return 'pendukung';
    }

    public function title(): string
    {
        return 'Laporan Pendukung Logistik & Gudang';
    }

    public function kop(): string
    {
        return 'LAPORAN PENDUKUNG';
    }

    public function description(): string
    {
        return 'Daftar dokumen pendukung laporan logistik & gudang beserta folder, detail dan keterangannya.';
    }

    public function orientation(): string
    {
        return 'portrait';
    }

    public function columns(): array
    {
        return [
            self::col('uraian', 'URAIAN', 'text', ['width' => 260]),
            self::col('folder', 'FOLDER'),
            self::col('detail', 'DETAIL'),
            self::col('keterangan', 'KETERANGAN'),
        ];
    }

    public function sections(): array
    {
        return [self::section('dokumen', null, array_map(fn (string $uraian): array => [
            'uraian' => $uraian,
            'keterangan' => $uraian === '2.1 REKAPITULASI KONDISI STOK MATERIAL' ? 'ROP' : '',
        ], self::URAIAN))];
    }
}
