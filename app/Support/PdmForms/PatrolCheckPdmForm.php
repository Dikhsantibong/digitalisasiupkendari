<?php

namespace App\Support\PdmForms;

use App\Models\Machine;
use App\Models\Unit;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;

/**
 * "Patrol Check Predictive Maintenance (PdM)": A. identitas patrol (unit,
 * hari/tanggal, waktu, tim & pelaksana) and B. the checklist of area/objek
 * items with their standard, ticked Ya / Tidak / N/A, plus temuan.
 */
class PatrolCheckPdmForm extends PdmForm
{
    /**
     * Default checklist: [area/objek, item pemeriksaan, standar/kriteria].
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    public const ITEMS = [
        ['Mesin Diesel', 'Tidak terdapat abnormal noise', 'Suara normal'],
        ['Mesin Diesel', 'Tidak terdapat abnormal vibration', 'Nilai sesuai standar'],
        ['Mesin Diesel', 'Tidak terjadi kebocoran oli', 'Tidak ada rembesan'],
        ['Mesin Diesel', 'Tidak terjadi kebocoran BBM', 'Aman'],
        ['Mesin Diesel', 'Temperatur mesin normal', 'Sesuai parameter operasi'],
        ['Generator', 'Temperatur bearing normal', 'Dalam batas standar'],
        ['Generator', 'Getaran bearing normal', 'Tidak melebihi alarm'],
        ['Generator', 'Tidak ada suara abnormal', 'Operasi normal'],
        ['Coupling', 'Tidak ada kerusakan', 'Baut lengkap & kencang'],
        ['Sistem Pelumasan', 'Tekanan oli normal', 'Sesuai SOP'],
        ['Sistem Pendingin', 'Temperatur cooling water normal', 'Sesuai standar'],
        ['Sistem Udara', 'Filter udara bersih', 'Tidak tersumbat'],
        ['Exhaust', 'Temperatur exhaust normal', 'Tidak melebihi batas'],
        ['Panel Kontrol', 'Alarm tidak aktif', 'Tidak ada alarm abnormal'],
        ['Panel Kontrol', 'Data monitoring lengkap', 'Data tersimpan'],
        ['Thermography', 'Pemeriksaan panel selesai', 'Tidak ada hotspot'],
        ['Thermography', 'Sambungan kabel normal', 'Tidak overheat'],
        ['Vibration Analysis', 'Pengukuran dilakukan', 'Data tersimpan'],
        ['Oil Analysis', 'Sampel oli diambil sesuai jadwal', 'Jadwal terpenuhi'],
        ['Oil Analysis', 'Hasil analisa tersedia', 'Tidak melebihi batas'],
        ['Battery', 'Tegangan baterai normal', 'Sesuai spesifikasi'],
        ['Charger Battery', 'Berfungsi normal', 'Output sesuai'],
        ['Instrumentasi', 'Sensor bekerja normal', 'Tidak ada fault'],
        ['Sistem Monitoring', 'Data online normal', 'Komunikasi lancar'],
        ['K3', 'APD digunakan', 'Sesuai ketentuan'],
        ['K3', 'Area kerja bersih', 'Housekeeping baik'],
        ['Dokumentasi', 'Hasil inspeksi terdokumentasi', 'Lengkap'],
        ['Work Order', 'Temuan dibuatkan WO', 'Bila diperlukan'],
    ];

    public function key(): string
    {
        return 'patrol-check-pdm';
    }

    public function title(): string
    {
        return 'Patrol Check Predictive Maintenance (PdM)';
    }

    public function description(): string
    {
        return 'Checklist patrol area mesin, generator, sistem bantu, panel & K3 dengan standar/kriteria, hasil Ya/Tidak/N/A, serta temuan.';
    }

    public function kopLines(string $unitName): array
    {
        return [
            'JASA PENUNJANG TEKNIS 11 SITE',
            'PLN NP UP KENDARI '.strtoupper($unitName),
            'BAGIAN PdM PEMBANGKIT',
            'PATROL CHECK PREDICTIVE MAINTENANCE (PdM)',
        ];
    }

    public function headerColor(): string
    {
        return 'navy';
    }

    public function orientation(): string
    {
        return 'portrait';
    }

    public function fields(): array
    {
        $identitas = 'A. Identitas Patrol';

        return [
            self::field('unit', 'Unit', 'text', 'header', $identitas),
            self::field('tanggal', 'Hari/Tanggal', 'date', 'header', $identitas),
            self::field('waktu', 'Waktu', 'text', 'header', $identitas),
            self::field('tim_patrol', 'Tim Patrol', 'text', 'header', $identitas),
            self::field('pelaksana', 'Pelaksana', 'text', 'header', $identitas),
            self::field('catatan', 'Catatan / Tindak Lanjut Patrol', 'textarea', 'footer'),
        ];
    }

    public function defaults(Unit $unit, int $month, int $year, ?Machine $machine): array
    {
        return [
            'unit' => $unit->name,
            'tanggal' => Carbon::create($year, $month, 1)->format('Y-m-d'),
            'waktu' => '10.00 WITA',
            'tim_patrol' => 'Officer K3L',
            'pelaksana' => 'Officer PdM',
        ];
    }

    public function sections(): array
    {
        $hasil = fn (string $key, string $label): array => self::col($key, $label, 'check', ['exclusive' => 'hasil', 'align' => 'c', 'width' => 34]);

        return [[
            'key' => 'checklist',
            'title' => 'B. Checklist Patrol PdM',
            'note' => 'Centang salah satu: Ya (sesuai standar), Tidak (tidak sesuai — tulis temuan), atau N/A (tidak berlaku).',
            'columns' => [
                self::col('area', 'Area/Objek', 'text', ['width' => 95]),
                self::col('item', 'Item Pemeriksaan'),
                self::col('standar', 'Standar/Kriteria'),
                $hasil('ya', 'Ya'),
                $hasil('tidak', 'Tidak'),
                $hasil('na', 'N/A'),
                self::col('temuan', 'Temuan / Keterangan'),
            ],
            'rows' => array_map(fn (array $item): array => ['area' => $item[0], 'item' => $item[1], 'standar' => $item[2]], self::ITEMS),
        ]];
    }

    /**
     * Rekap hasil patrol: jumlah item Ya / Tidak / N/A / belum diisi.
     */
    public function summary(array $rows): ?array
    {
        $list = $rows['checklist'] ?? [];
        $count = fn (string $key): int => count(array_filter($list, fn (array $row): bool => ($row[$key] ?? null) === '1'));
        $ya = $count('ya');
        $tidak = $count('tidak');
        $na = $count('na');
        $filled = $ya + $tidak + $na;

        return [
            'title' => 'Rekap Hasil Patrol',
            'columns' => ['Total Item', 'Ya', 'Tidak', 'N/A', 'Belum Diisi', '% Sesuai'],
            'rows' => [[
                (string) count($list),
                (string) $ya,
                (string) $tidak,
                (string) $na,
                (string) (count($list) - $filled),
                ($ya + $tidak) > 0 ? round($ya / ($ya + $tidak) * 100).'%' : '-',
            ]],
        ];
    }

    /**
     * Label hari & tanggal patrol, mis. "Jumat, 10 Juli 2026".
     */
    public static function dayLabel(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $parsed = Carbon::parse($date);

        return Indonesian::dayName($parsed).', '.Indonesian::longDate($parsed);
    }
}
