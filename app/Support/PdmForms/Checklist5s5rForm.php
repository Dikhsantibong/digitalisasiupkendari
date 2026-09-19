<?php

namespace App\Support\PdmForms;

use App\Models\Machine;
use App\Models\Unit;
use App\Support\Indonesian;

/**
 * "Laporan Inspeksi Checklist 5S5R" Bagian PdM: identitas, then the five 5S5R
 * checklists (Ringkas, Rapi, Resik, Rawat, Rajin) with rencana/realisasi,
 * temuan, tindak lanjut & eviden, closed by the akumulatif kinerja per 5S.
 */
class Checklist5s5rForm extends PdmForm
{
    private const ITEMS = [
        'ringkas' => [
            'title' => '1. RINGKAS (SEIRI)',
            'note' => 'Tujuan: Memastikan hanya peralatan dan material operasi yang diperlukan berada di area kerja.',
            'items' => ['Peralatan ukur hanya yang digunakan tersedia', 'Alat rusak dipisahkan', 'Material bekas dibuang', 'Dokumen lama diarsipkan', 'Area bebas barang tidak diperlukan'],
        ],
        'rapi' => [
            'title' => '2. RAPI (SEITON)',
            'note' => 'Tujuan: Menata seluruh peralatan operasi agar mudah dicari.',
            'items' => ['Radio Komunikasi (HT) pada tempatnya', 'Handphone (HP) Komunikasi pada tempatnya', 'Computer Personal (PC) pada tempatnya', 'Termo Gun pada tempatnya', 'Toolkit lengkap', 'Dokumen Logsheet Manual lama tertata rapi', 'Rak penyimpanan IK & SOP rapi', 'Label aset lengkap'],
        ],
        'resik' => [
            'title' => '3. RESIK (SEISO)',
            'note' => 'Tujuan: Menjaga kebersihan area Power House & CCR Operator.',
            'items' => ['Panel bersih dari debu', 'Generator bersih', 'Turbin bersih', 'Oil Cooler bersih', 'Bearing bersih', 'Tidak ada kebocoran oli', 'Area inspeksi bersih'],
        ],
        'rawat' => [
            'title' => '4. RAWAT (SEIKETSU)',
            'note' => 'Tujuan: Menjaga standar pelaksanaan Operasi.',
            'items' => ['Checklist Operasi tersedia', 'SOP tersedia', 'Instruksi Kerja tersedia', 'Toolkit & Peralatan Operasi dalam kondisi Baik', 'Jadwal Operasi berjalan', 'History Equipment diperbarui', 'Data Input Online Laporan lengkap'],
        ],
        'rajin' => [
            'title' => '5. RAJIN (SHITSUKE)',
            'note' => 'Tujuan: Membentuk disiplin pelaksanaan PDM.',
            'items' => ['Inspeksi tepat waktu', 'Menggunakan APD lengkap', 'Data diinput tepat waktu', 'Temuan abnormal segera dilaporkan', 'Tindak lanjut selesai sesuai target', 'Briefing dilaksanakan', 'Housekeeping setelah pekerjaan'],
        ],
    ];

    public function key(): string
    {
        return 'checklist-5s5r';
    }

    public function title(): string
    {
        return 'Laporan Inspeksi Checklist 5S5R PdM';
    }

    public function description(): string
    {
        return 'Penilaian berkala budaya kerja Ringkas, Rapi, Resik, Rawat, Rajin: rencana & realisasi, temuan, tindak lanjut, dan eviden.';
    }

    public function kopLines(string $unitName): array
    {
        return ['JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE-KIT', 'PLN NP UP KENDARI '.strtoupper($unitName), 'LAPORAN INSPEKSI CHECKLIST 5S5R', 'BAGIAN PdM PEMBANGKIT'];
    }

    public function fields(): array
    {
        return [
            self::field('nama_pembangkit', 'Nama Pembangkit', group: 'A. IDENTITAS'),
            self::field('bulan', 'Bulan', group: 'A. IDENTITAS'),
        ];
    }

    public function defaults(Unit $unit, int $month, int $year, ?Machine $machine): array
    {
        return ['nama_pembangkit' => $unit->name, 'bulan' => Indonesian::monthName($month).' '.$year];
    }

    public function sections(): array
    {
        $columns = [
            self::col('item', 'Item Pemeriksaan', 'text', ['width' => 170]),
            self::col('rencana', 'Rencana', 'number', ['align' => 'c', 'width' => 50]),
            self::col('realisasi', 'Realisasi', 'number', ['align' => 'c', 'width' => 55]),
            self::col('temuan', 'Temuan'),
            self::col('tindak_lanjut', 'Tindak Lanjut'),
            self::col('eviden', 'Eviden'),
        ];

        $sections = [];
        foreach (self::ITEMS as $key => $spec) {
            $sections[] = [
                'key' => $key,
                'title' => $spec['title'],
                'note' => $spec['note'],
                'columns' => $columns,
                'rows' => array_map(fn (string $item): array => ['item' => $item, 'rencana' => '1', 'realisasi' => null], $spec['items']),
                'totals' => ['rencana', 'realisasi'],
            ];
        }

        return $sections;
    }

    public function summary(array $rows): ?array
    {
        $table = [];
        foreach (self::ITEMS as $key => $spec) {
            $rencana = array_sum(array_map(fn (array $r): float => (float) ($r['rencana'] ?? 0), $rows[$key] ?? []));
            $realisasi = array_sum(array_map(fn (array $r): float => (float) ($r['realisasi'] ?? 0), $rows[$key] ?? []));
            $table[] = [
                strtoupper(strtok(substr($spec['title'], 3), ' ')),
                (string) (int) $rencana,
                (string) (int) $realisasi,
                $rencana > 0 ? round($realisasi / $rencana * 100).'%' : '-',
            ];
        }

        return ['title' => 'AKUMULATIF', 'columns' => ['Akumulatif', 'Rencana', 'Realisasi', 'A. Kinerja'], 'rows' => $table];
    }
}
