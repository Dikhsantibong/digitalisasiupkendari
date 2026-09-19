<?php

namespace App\Support\LogistikForms;

/**
 * Laporan Kondisi Stok Tools dan Material: per material the stok awal,
 * masuk, keluar and stok akhir, satuan, harga, pemakaian rata-rata, safety
 * stock, ILT, ROP (pemakaian rata-rata × ILT + safety stock) and RoQ. The
 * material section starts from the standard consumable list.
 */
class KondisiStokForm extends LogistikForm
{
    /** @var list<array{0: string, 1: string}> nama, satuan */
    public const MATERIAL = [
        ['Air Aki Tambah kemasan 1L/Btl Reff Yuasa', 'Botol'],
        ['Air Aki Zuur 1L/btl', 'Botol'],
        ['Alat Pel', 'Bh'],
        ['Amplas Grip 80', 'Lembar'],
        ['Amplas Grip 150', 'Lembar'],
        ['Amplas Grip 400', 'Lembar'],
        ['Amplas Grip 500', 'Lembar'],
        ['Amplas Grip 800', 'Lembar'],
        ['Amplas Grip 1000', 'Lembar'],
        ['Autosol', 'Bh'],
        ['Bohlam Lampu Heater 300 watt', 'Pcs'],
        ['Baterai AA', 'Bh'],
        ['Baterai AAA', 'Bh'],
        ['Baterai Kotak 9 Volt', 'Bh'],
        ['Botol (BOTTLE, OIL SAMPLE) 120ml', 'Bh'],
        ['Brasso', 'Bh'],
        ['Cat Kaleng Abu-Abu', 'Kaleng'],
        ['Contact Cleaner @360 ml', 'Kaleng'],
        ['Earmuff', 'Dus'],
        ['Gas Touch', 'Bh'],
        ['Gas Portable (Winn Gas Butane @235gr)', 'Kaleng'],
        ['Gris', 'Kaleng'],
        ['Gagang Sapu', 'Bh'],
        ['Isolasi 20 Kv Scotch 3M', 'Bh'],
        ['Isolasi Listrik 0.13mmx19mmx20mtr Unibel', 'Bh'],
        ['Kabel Serabut 2 x 1,5 mm', 'Meter'],
        ['Kabel Ties 10 Cm @100pcs/pack', 'Bungkus'],
        ['Kabel Ties 20 Cm @100pcs/pack', 'Bungkus'],
        ['Kabel Ties 30 Cm @100pcs/pack', 'Bungkus'],
        ['Kaos Tangan (Bintik)', 'Bungkus'],
        ['Kawat Las RD-260 2,0 mm', 'Kg'],
        ['Klem Kabel Nomor 9 @100pcs/pack', 'Pack'],
        ['Keran Air', 'Bh'],
        ['Kunci Inggris', 'Bh'],
        ['Lampu Sorot 500W', 'Bh'],
        ['Lampu Merkuri 160W', 'Bh'],
        ['Lampu LED 40 watt Reff Philips', 'Bh'],
        ['Lampu LED 60 Watt', 'Bh'],
        ['Lem Besi Epoxy @48gr (Dextone)', 'Bh'],
        ['Lem Castol', 'Bh'],
        ['Lem Epoxy Avian Super (Resin & Hardness)', 'Kaleng'],
        ['Lem Korea @30gr', 'Bh'],
        ['Lem RTV High Temperature Dextone @30gr', 'Bh'],
        ['Lem RTV High Temperature Dextone @75gr', 'Bh'],
        ['Lem Loctite', 'Bh'],
        ['Lem Pipa', 'Bh'],
        ['Majun Putih Kain Perca', 'Kg'],
        ['Majun Handuk', 'Lembar'],
        ['Masker', 'Dus'],
        ['Mata Gurinda Potong Kecil Tipis 4"', 'Dus'],
        ['Mata Gurinda Amplas Kasar', 'Lembar'],
        ['Mata Gurinda Amplas Halus', 'Lembar'],
        ['Mata Cutter', 'Pcs'],
        ['Mistar 60cm', 'Bh'],
    ];

    public const SAFETY_STOCK = 2;

    public const ILT = 3;

    public function key(): string
    {
        return 'kondisi-stok';
    }

    public function title(): string
    {
        return 'Laporan Kondisi Stok Tools dan Material';
    }

    public function kop(): string
    {
        return 'LAPORAN KONDISI STOK TOOLS DAN MATERIAL';
    }

    public function description(): string
    {
        return 'Stok awal, masuk, keluar dan stok akhir tools & material, dengan safety stock, ILT, ROP dan RoQ.';
    }

    public function columns(): array
    {
        $number = ['align' => 'c', 'width' => 50];

        return [
            self::col('kode_material', 'Kode Material', 'text', ['width' => 60]),
            self::col('stock_code', 'Stock Code', 'text', ['width' => 60]),
            self::col('nama', 'Nama Material/Consumable', 'text', ['width' => 190]),
            self::col('stok_awal', 'Stok Awal', 'number', $number),
            self::col('material_masuk', 'Material Masuk', 'number', $number),
            self::col('material_keluar', 'Material Keluar', 'number', $number),
            self::col('stok_akhir', 'Stok Akhir', 'computed', [...$number, 'computed' => ['formula' => 'stok_awal + material_masuk - material_keluar']]),
            self::col('satuan', 'Satuan', 'text', ['align' => 'c', 'width' => 50]),
            self::col('harga_satuan', 'Harga Satuan', 'number', ['align' => 'r', 'width' => 64]),
            self::col('pemakaian_rata', 'Pemakaian Rata-rata', 'number', $number),
            self::col('safety_stock', 'Safety Stock', 'number', [...$number, 'default' => self::SAFETY_STOCK]),
            self::col('ilt', 'ILT', 'number', [...$number, 'default' => self::ILT]),
            self::col('rop', 'ROP', 'computed', [...$number, 'computed' => ['formula' => 'pemakaian_rata * ilt + safety_stock']]),
            self::col('roq', 'RoQ', 'number', $number),
        ];
    }

    public function sections(): array
    {
        return [
            self::section('peralatan', 'A. PERALATAN', [], 8),
            self::section('material', 'B. MATERIAL', array_map(fn (array $m): array => [
                'nama' => $m[0], 'satuan' => $m[1], 'stok_awal' => 0, 'safety_stock' => self::SAFETY_STOCK, 'ilt' => self::ILT,
            ], self::MATERIAL)),
        ];
    }
}
