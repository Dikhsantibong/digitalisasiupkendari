<?php

namespace App\Support\PdmForms;

use App\Models\Machine;
use App\Models\Unit;

/**
 * "Dokumen/Laporan Checklist Patrol Check PdM": identitas (pembangkit,
 * petugas, tanggal, shift, supervisor), items per area A–E numbered 1..n
 * across the sections with status OK / NOK / N/A, temuan, tindakan & eviden,
 * the ringkasan hasil patroli, catatan khusus and eviden photos.
 */
class ChecklistPatrolCheckPdmForm extends PdmForm
{
    public const STATUS = ['OK', 'NOK', 'N/A'];

    /**
     * Default item & area pemeriksaan per section.
     *
     * @var array<string, array{title: string, items: list<string>}>
     */
    public const AREAS = [
        'turbin' => ['title' => 'A. TURBIN', 'items' => [
            'Bearing Turbin',
            'Suhu bearing normal',
            'Shaft Turbin: bunyi abnormal',
            'Rumah Bearing: kebocoran oli/grease',
        ]],
        'generator' => ['title' => 'B. GENERATOR', 'items' => [
            'Bearing Generator: temperatur',
            'Kebersihan Ventilasi Generator',
        ]],
        'pelumasan' => ['title' => 'C. SISTEM PELUMASAN', 'items' => [
            'Tangki: level oli',
            'Warna oli pelumas',
            'Oli Pelumas: kontaminasi air',
            'Kondisi kebersihan filter oli',
        ]],
        'pendingin' => ['title' => 'D. SISTEM PENDINGIN', 'items' => [
            'Kondisi Oil Cooler tidak bocor',
            'Cooling Water',
            'Temperatur oli sebelum/sesudah cooler',
        ]],
        'monitoring' => ['title' => 'E. MONITORING PdM', 'items' => [
            'Pelaksanaan pengukuran getaran',
            'Pemeriksaan kebocoran',
            'Pemeriksaan temperatur',
            'Pengambilan dan analisis sampel oli',
        ]],
    ];

    public function key(): string
    {
        return 'checklist-patrol-check';
    }

    public function title(): string
    {
        return 'Laporan Checklist Patrol Check PdM';
    }

    public function description(): string
    {
        return 'Checklist patrol per area (turbin, generator, pelumasan, pendingin, monitoring PdM) dengan status OK/NOK/N/A, temuan, tindak lanjut, dan eviden.';
    }

    public function kopLines(string $unitName): array
    {
        return [
            'JASA PENDUKUNG TEKNIS UP KENDARI 11 - 6 SITE',
            'PLN NP UP KENDARI '.strtoupper($unitName),
            'DOKUMEN/LAPORAN CHECKLIST PATROL CHECK PdM',
            'BAGIAN PdM PEMBANGKIT',
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

    public function continuousNumbering(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [
            self::field('nama_pembangkit', 'Nama Pembangkit'),
            self::field('tanggal_patroli', 'Tanggal Patroli', 'date'),
            self::field('petugas_patroli', 'Petugas Patroli'),
            self::field('shift_waktu', 'Shift / Waktu'),
            self::field('supervisor', 'Supervisor / Evaluator'),
            self::field('catatan', 'Catatan Khusus & Evaluasi Petugas', 'textarea', 'footer'),
            self::field('eviden', 'Eviden (Foto)', 'images', 'footer'),
        ];
    }

    public function defaults(Unit $unit, int $month, int $year, ?Machine $machine): array
    {
        return [
            'nama_pembangkit' => $unit->name,
            'petugas_patroli' => 'PIC PDM',
        ];
    }

    public function sections(): array
    {
        $columns = [
            self::col('item', 'Item & Area Pemeriksaan', 'text', ['width' => 150]),
            self::col('status', 'Status', 'select', ['options' => self::STATUS, 'align' => 'c', 'width' => 50]),
            self::col('keterangan', 'Keterangan / Temuan Lapangan'),
            self::col('tindakan', 'Tindakan Perbaikan / Tindak Lanjut'),
            self::col('eviden', 'Eviden (Foto)', 'text', ['width' => 70]),
        ];

        return array_map(fn (string $key, array $area): array => [
            'key' => $key,
            'title' => $area['title'],
            'columns' => $columns,
            'rows' => array_map(fn (string $item): array => ['item' => $item], $area['items']),
        ], array_keys(self::AREAS), self::AREAS);
    }

    /**
     * Ringkasan hasil patroli: total item, sesuai (OK), temuan (NOK), N/A,
     * belum diisi dan realisasi (item yang sudah diperiksa).
     */
    public function summary(array $rows): ?array
    {
        $statuses = array_map(fn (array $row): ?string => $row['status'] ?? null, array_merge(...array_values($rows ?: [[]])));
        $total = count($statuses);
        $count = fn (string $status): int => count(array_filter($statuses, fn (?string $value): bool => $value === $status));
        $checked = $count('OK') + $count('NOK') + $count('N/A');

        return [
            'title' => 'Ringkasan Hasil Patroli',
            'columns' => ['Total Item', 'Sesuai (OK)', 'Temuan (NOK)', 'N/A', 'Belum Diisi', 'Realisasi'],
            'rows' => [[
                (string) $total,
                (string) $count('OK'),
                (string) $count('NOK'),
                (string) $count('N/A'),
                (string) ($total - $checked),
                $total > 0 ? round($checked / $total * 100).'%' : '-',
            ]],
        ];
    }
}
