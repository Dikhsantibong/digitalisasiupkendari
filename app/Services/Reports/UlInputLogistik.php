<?php

namespace App\Services\Reports;

use App\Models\LogistikFormRow;
use App\Models\Unit;
use App\Support\LogistikForms\InputLogistikForm;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Input Logistik of the TL Pemeliharaan / TL Ophar ({@see InputLogistikForm},
 * Pemeliharaan Akses 2) of every unit under the UL, for the Laporan Logistik of
 * the Laporan UL (user, 2026-11-19): a recap per unit (items, stock value, items
 * at/below ROP, items per Dasar Stok), the detail rows and the matching
 * insights. Only saved rows count — never the form's default rows.
 */
class UlInputLogistik
{
    public function __construct(private readonly ModuleResume $resume) {}

    /**
     * @param  Collection<int, Unit>  $units
     * @return array{sections: list<array<string, mixed>>, insights: list<array{tone: string, text: string}>}
     */
    public function build(Collection $units, int $month, int $year): array
    {
        $form = new InputLogistikForm;
        $saved = LogistikFormRow::query()
            ->where('form', $form->key())->where('year', $year)->where('month', $month)
            ->whereIn('unit_id', $units->modelKeys())
            ->orderBy('unit_id')->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('unit_id');

        $number = fn (mixed $value): float => is_numeric($value) ? (float) $value : 0.0;
        $fmt = fn (float $value, string $format = 'number'): string => $this->resume->formatValue($value, $format);
        $sectionLabel = ['peralatan' => 'Peralatan', 'material' => 'Material'];

        $recap = [];
        $detail = [];
        $totals = ['items' => 0, 'value' => 0.0, 'below' => 0, 'dasar' => array_fill_keys(InputLogistikForm::DASAR_STOK, 0)];

        foreach ($units as $unit) {
            $rows = ($saved->get($unit->id) ?? collect())->map(fn (LogistikFormRow $row): array => ['section' => $row->section, 'data' => $form->compute($row->data)]);
            $value = 0.0;
            $below = 0;
            $dasar = array_fill_keys(InputLogistikForm::DASAR_STOK, 0);

            foreach ($rows as $row) {
                $d = $row['data'];
                $stokAkhir = $number($d['stok_akhir'] ?? null);
                $value += $stokAkhir * $number($d['harga_satuan'] ?? null);
                // At or below the reorder point: time to order (only when a ROP is set).
                if ($number($d['rop'] ?? null) > 0 && $stokAkhir <= $number($d['rop'] ?? null)) {
                    $below++;
                }
                if (isset($dasar[$d['dasar_stok'] ?? ''])) {
                    $dasar[$d['dasar_stok']]++;
                }

                $detail[] = [
                    $unit->name,
                    $sectionLabel[$row['section']] ?? (string) $row['section'],
                    (string) ($d['nama'] ?? ''),
                    (string) ($d['dasar_stok'] ?? '—'),
                    (string) ($d['nomor_surat'] ?? '—'),
                    $fmt($number($d['stok_awal'] ?? null)),
                    $fmt($number($d['material_masuk'] ?? null)),
                    $fmt($number($d['material_keluar'] ?? null)),
                    $fmt($stokAkhir),
                    (string) ($d['satuan'] ?? ''),
                    $fmt($number($d['harga_satuan'] ?? null), 'rupiah'),
                    $fmt($number($d['rop'] ?? null)),
                    $fmt($number($d['roq'] ?? null)),
                ];
            }

            $recap[] = [$unit->name, $fmt($rows->count()), $fmt($value, 'rupiah'), $fmt($below), ...array_map($fmt, array_values($dasar))];
            $totals['items'] += $rows->count();
            $totals['value'] += $value;
            $totals['below'] += $below;
            foreach ($dasar as $key => $count) {
                $totals['dasar'][$key] += $count;
            }
        }

        $sections = [
            [
                'title' => 'Input Logistik TL — Rekap Stok Tools & Material per Unit',
                'columns' => ['Unit', 'Jumlah Item', 'Nilai Stok', 'Item ≤ ROP', ...InputLogistikForm::DASAR_STOK],
                'rows' => $recap,
                'footer' => ['TOTAL UL', $fmt($totals['items']), $fmt($totals['value'], 'rupiah'), $fmt($totals['below']), ...array_map($fmt, array_values($totals['dasar']))],
                'note' => 'Sumber: Input Logistik (Input Pemeliharaan) yang diisi TL Pemeliharaan / TL Ophar & Staf tiap unit. Nilai stok = stok akhir × harga satuan.',
            ],
            [
                'title' => 'Input Logistik TL — Rincian Stok, Dasar Stok & Nomor Surat / Kontrak',
                'columns' => ['Unit', 'Kelompok', 'Nama Material / Tools', 'Dasar Stok', 'Nomor Surat / Kontrak', 'Stok Awal', 'Masuk', 'Keluar', 'Stok Akhir', 'Satuan', 'Harga Satuan', 'ROP', 'RoQ'],
                'rows' => $detail,
            ],
        ];

        $insights = [];
        $unfilled = collect($recap)->filter(fn (array $row): bool => $row[1] === '0')->pluck(0);
        if ($totals['items'] > 0) {
            $insights[] = ['tone' => 'info', 'text' => "Input Logistik TL: {$fmt($totals['items'])} item tercatat dengan nilai stok {$fmt($totals['value'], 'rupiah')}."];
        }
        if ($totals['below'] > 0) {
            $insights[] = ['tone' => 'warning', 'text' => "{$fmt($totals['below'])} item stok tools & material sudah di bawah atau sama dengan ROP — perlu pemesanan ulang."];
        }
        if ($unfilled->isNotEmpty()) {
            $insights[] = ['tone' => 'neutral', 'text' => 'Input Logistik TL belum diisi: '.$unfilled->join(', ').'.'];
        }

        return ['sections' => $sections, 'insights' => $insights];
    }
}
