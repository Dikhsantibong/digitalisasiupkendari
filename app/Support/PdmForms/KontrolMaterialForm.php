<?php

namespace App\Support\PdmForms;

use App\Models\Machine;
use App\Models\Unit;

/**
 * "Form Kontrol Material, Peralatan dan Tools PdM Pembangkit": serah terima
 * shift header, then the material (A), peralatan (B) and tools (C) checked
 * against their standard quantity, and the serah terima notes (D).
 */
class KontrolMaterialForm extends PdmForm
{
    public const KONDISI = ['Baik', 'Rusak', 'Perlu Perbaikan'];

    public const STATUS = ['Tersedia', 'Kurang', 'Tidak Tersedia'];

    public function key(): string
    {
        return 'kontrol-material';
    }

    public function title(): string
    {
        return 'Form Kontrol Material, Peralatan & Tools PdM';
    }

    public function description(): string
    {
        return 'Kontrol inventaris material pelumas, peralatan komunikasi/senter/kunci panel, kelayakan tool set, dan serah terima shift.';
    }

    public function kopLines(string $unitName): array
    {
        return ['FORM KONTROL MATERIAL, PERALATAN DAN TOOLS PdM PEMBANGKIT', strtoupper($unitName)];
    }

    public function theme(): string
    {
        return 'navy';
    }

    public function headerColor(): string
    {
        return 'navy';
    }

    public function fields(): array
    {
        return [
            self::field('unit_lokasi', 'Unit / Lokasi'),
            self::field('shift_sebelumnya', 'Shift Sebelumnya'),
            self::field('operator_serah_terima', 'Nama Operator Serah Terima'),
            self::field('jam_serah_terima', 'Jam Serah Terima', 'time'),
            self::field('catatan', 'D. Catatan Serah Terima / Temuan', 'textarea', 'footer'),
        ];
    }

    public function defaults(Unit $unit, int $month, int $year, ?Machine $machine): array
    {
        return ['unit_lokasi' => $unit->name];
    }

    public function sections(): array
    {
        $columns = [
            self::col('nama', 'Nama Material', 'text', ['width' => 130]),
            self::col('spesifikasi', 'Spesifikasi / Ukuran'),
            self::col('lokasi', 'Lokasi Penyimpanan'),
            self::col('jumlah_standar', 'Jumlah Standar', 'number', ['align' => 'c', 'width' => 55]),
            self::col('jumlah_aktual', 'Jumlah Aktual', 'number', ['align' => 'c', 'width' => 55]),
            self::col('satuan', 'Satuan', 'text', ['align' => 'c', 'width' => 45]),
            self::col('kondisi', 'Kondisi', 'select', ['options' => self::KONDISI, 'align' => 'c', 'width' => 65]),
            self::col('status', 'Status', 'select', ['options' => self::STATUS, 'align' => 'c', 'width' => 65]),
            self::col('keterangan', 'Keterangan'),
        ];
        $rows = fn (array $items): array => array_map(fn (array $item): array => ['nama' => $item[0], 'satuan' => $item[1]], $items);

        return [
            ['key' => 'material', 'title' => 'A. KONTROL MATERIAL PdM', 'columns' => $columns, 'blank_rows' => 5,
                'rows' => $rows([['Oli Mesin', 'Liter'], ['Solar / BBM Operasi', 'Liter'], ['Grease', 'Kg']])],
            ['key' => 'peralatan', 'title' => 'B. KONTROL PERALATAN PdM', 'columns' => $columns, 'blank_rows' => 6,
                'rows' => $rows([['Handy Talky / Radio Komunikasi', 'Unit'], ['Senter', 'Unit'], ['Kunci Panel / Kunci Ruang Operasi', 'Set'], ['Alat Komunikasi / HP Piket', 'Unit']])],
            ['key' => 'tools', 'title' => 'C. KONTROL TOOLS PdM', 'columns' => $columns, 'blank_rows' => 6,
                'rows' => $rows([['Tool Set', 'Set'], ['Multimeter / AVO Meter', 'Unit'], ['Tang / Obeng / Kunci', 'Set']])],
        ];
    }
}
