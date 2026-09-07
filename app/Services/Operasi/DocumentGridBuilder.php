<?php

namespace App\Services\Operasi;

use App\Enums\BeritaAcaraType;

/**
 * Turns a generated document into a spreadsheet grid (cells + merges + column
 * widths) for the Excel edit mode, and renders a saved grid back to an HTML
 * table for PDF export. Cell text is pre-formatted so the spreadsheet, the
 * downloaded .xlsx, and the PDF all read identically.
 *
 * Grid shape: array{name, cols, col_widths, merges: list<array{0..3:int}>,
 *   rows: list<list<array{t:string, b?:bool, a?:string}>>}
 */
class DocumentGridBuilder
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function forBeritaAcara(BeritaAcaraType $type, array $data): array
    {
        return $type->isFuel()
            ? $this->fuelGrid($data)
            : $this->lubricantGrid($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fuelGrid(array $data): array
    {
        $cols = 2;
        $rows = [];
        $merges = [];
        $mergeFull = function (int $r) use (&$merges, $cols): void {
            $merges[] = [$r, 0, $r, $cols - 1];
        };

        $push = function (array $row) use (&$rows): int {
            $rows[] = $row;

            return count($rows) - 1;
        };

        $mergeFull($push([$this->c($data['document']['title'], true, 'c')]));
        $mergeFull($push([$this->c('NO : '.$data['document']['number'], true, 'l')]));
        $mergeFull($push([$this->c($this->narrative($data, 'Bahan Bakar Minyak '.$data['fuel_label']))]));

        $fmt = fn ($v): string => number_format((float) $v, 2, ',', '.');

        $push([$this->c('1. Persediaan Awal'), $this->c($fmt($data['persediaan_awal']).' Liter', false, 'r')]);
        $push([$this->c('2. Penerimaan BBM ('.$data['fuel_label'].')'), $this->c('')]);
        $push([$this->c('   '.$data['penerimaan_range'].' pukul 10.00'), $this->c($fmt($data['penerimaan_total']).' Liter', false, 'r')]);
        $push([$this->c('A. Jumlah Stock BBM '.$data['fuel_label'], true), $this->c($fmt($data['jumlah_stock']).' Liter', true, 'r')]);
        $push([$this->c('3. Pemakaian Mesin PLN'), $this->c('')]);
        foreach ($data['pemakaian'] as $item) {
            $push([$this->c('   '.$item['mesin']), $this->c($fmt($item['liter']).' Liter', false, 'r')]);
        }
        $push([$this->c('B. Jumlah Pemakaian (3)', true), $this->c($fmt($data['pemakaian_total']).' Liter', true, 'r')]);
        $push([$this->c('C. Jumlah Pengiriman', true), $this->c($fmt($data['pengiriman']).' Liter', true, 'r')]);
        $push([$this->c('D. Persediaan menurut Administrasi (A-B-C)', true), $this->c($fmt($data['administrasi']).' Liter', true, 'r')]);
        $push([$this->c('Jumlah Persediaan menurut Fisik:'), $this->c('')]);
        foreach ($data['fisik'] as $item) {
            $push([$this->c('   '.$item['tangki']), $this->c($fmt($item['liter']).' Liter', false, 'r')]);
        }
        $push([$this->c('E. Jumlah Persediaan menurut Fisik', true), $this->c($fmt($data['fisik_total']).' Liter', true, 'r')]);
        $push([$this->c('F. Selisih Administrasi vs Fisik (E-D)', true), $this->c($fmt($data['selisih']).' Liter', true, 'r')]);
        $mergeFull($push([$this->c('Catatan: * Selisih disebabkan karena: ......................................')]));
        $push([$this->c(''), $this->c($data['print_place_date'], false, 'c')]);
        $push([$this->c('Menyetujui, Manajer', false, 'c'), $this->c('Membuat, TL. Operasi', false, 'c')]);
        $push([$this->c($data['signers']['manajer'] ?? '(………………)', true, 'c'), $this->c($data['signers']['tl_operasi'] ?? '(………………)', true, 'c')]);

        return [
            'name' => $data['document']['title'],
            'cols' => $cols,
            'col_widths' => [430, 160],
            'merges' => $merges,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function lubricantGrid(array $data): array
    {
        $cols = 9;
        $rows = [];
        $merges = [];
        $mergeFull = function (int $r) use (&$merges, $cols): void {
            $merges[] = [$r, 0, $r, $cols - 1];
        };
        $push = function (array $row) use (&$rows): int {
            $rows[] = $row;

            return count($rows) - 1;
        };
        $fmt = fn ($v): string => number_format((float) $v, 2, ',', '.');

        $mergeFull($push([$this->c($data['document']['title'], true, 'c')]));
        $mergeFull($push([$this->c('NO : '.$data['document']['number'], true, 'l')]));
        $mergeFull($push([$this->c($this->narrative($data, 'fisik pelumas'))]));

        $headers = ['Jenis Pelumas', 'Persediaan Awal', 'Penerimaan', 'Stock', 'Pemakaian Sendiri', 'Pengiriman', 'Persediaan Administrasi', 'Stock Fisik', 'Selisih Fisik-Adm'];
        $push(array_map(fn (string $h): array => $this->c($h, true, 'c'), $headers));

        $totals = array_fill_keys(['awal', 'penerimaan', 'stock', 'pemakaian', 'pengiriman', 'administrasi', 'fisik_liter', 'selisih'], 0.0);
        foreach ($data['rows'] as $row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += (float) $row[$key];
            }
            $push([
                $this->c($row['jenis']),
                $this->c($fmt($row['awal']), false, 'r'),
                $this->c($fmt($row['penerimaan']), false, 'r'),
                $this->c($fmt($row['stock']), false, 'r'),
                $this->c($fmt($row['pemakaian']), false, 'r'),
                $this->c($fmt($row['pengiriman']), false, 'r'),
                $this->c($fmt($row['administrasi']), false, 'r'),
                $this->c($fmt($row['fisik_liter']), false, 'r'),
                $this->c($fmt($row['selisih']), false, 'r'),
            ]);
        }
        $push([
            $this->c('JUMLAH TOTAL', true),
            $this->c($fmt($totals['awal']), true, 'r'),
            $this->c($fmt($totals['penerimaan']), true, 'r'),
            $this->c($fmt($totals['stock']), true, 'r'),
            $this->c($fmt($totals['pemakaian']), true, 'r'),
            $this->c($fmt($totals['pengiriman']), true, 'r'),
            $this->c($fmt($totals['administrasi']), true, 'r'),
            $this->c($fmt($totals['fisik_liter']), true, 'r'),
            $this->c($fmt($totals['selisih']), true, 'r'),
        ]);
        $mergeFull($push([$this->c('Catatan: * Selisih disebabkan karena: ......................................')]));

        return [
            'name' => 'BA Opname Pelumas',
            'cols' => $cols,
            'col_widths' => [150, 80, 70, 70, 90, 70, 100, 80, 90],
            'merges' => $merges,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data  the MonthlyEngineReport payload
     * @return array<string, mixed>
     */
    public function forMonthlyReport(array $data): array
    {
        $usesMfo = collect($data['rows'])->contains(fn (array $r): bool => $r['pemakaian_mfo'] !== null);

        $headers = ['Tgl', 'kWh Produksi', 'kWh PS', 'kWh Netto', 'Pakai HSD (L)'];
        if ($usesMfo) {
            $headers[] = 'Pakai MFO (L)';
        }
        $headers = array_merge($headers, ['Pelumas (L)', 'BP Pagi', 'BP Malam']);
        $cols = count($headers);

        $rows = [];
        $merges = [];
        $push = function (array $row) use (&$rows): int {
            $rows[] = $row;

            return count($rows) - 1;
        };
        $merges[] = [0, 0, 0, $cols - 1];
        $push([$this->c(($data['unit']['service_unit'] ?? 'UNIT LAYANAN'), true, 'c')]);
        $merges[] = [1, 0, 1, $cols - 1];
        $push([$this->c('LAPORAN OPERASI BULANAN — '.($data['engine']['name'] ?? '').' · '.$data['period']['label'], true, 'c')]);
        $push(array_map(fn (string $h): array => $this->c($h, true, 'c'), $headers));

        $fmt = fn ($v): string => $v === null ? '' : number_format((float) $v, 2, ',', '.');

        foreach ($data['rows'] as $row) {
            $cells = [
                $this->c((string) $row['day'], false, 'c'),
                $this->c($fmt($row['kwh_produksi']), false, 'r'),
                $this->c($fmt($row['kwh_pakai_sendiri']), false, 'r'),
                $this->c($fmt($row['kwh_netto']), false, 'r'),
                $this->c($fmt($row['pemakaian_hsd']), false, 'r'),
            ];
            if ($usesMfo) {
                $cells[] = $this->c($fmt($row['pemakaian_mfo']), false, 'r');
            }
            $cells[] = $this->c($fmt($row['pemakaian_pelumas_liter']), false, 'r');
            $cells[] = $this->c($fmt($row['beban_puncak_pagi_kw']), false, 'r');
            $cells[] = $this->c($fmt($row['beban_puncak_malam_kw']), false, 'r');
            $push($cells);
        }

        foreach (['periode_1' => 'PERIODE I', 'periode_2' => 'PERIODE II', 'periode_3' => 'PERIODE III', 'total' => 'TOTAL'] as $key => $label) {
            $s = $data['summary'][$key] ?? null;
            if ($s === null) {
                continue;
            }
            $cells = [
                $this->c($label, true, 'l'),
                $this->c($fmt($s['kwh_produksi']), true, 'r'),
                $this->c($fmt($s['kwh_pakai_sendiri']), true, 'r'),
                $this->c($fmt($s['kwh_netto']), true, 'r'),
                $this->c($fmt($s['pemakaian_hsd']), true, 'r'),
            ];
            if ($usesMfo) {
                $cells[] = $this->c($fmt($s['pemakaian_mfo']), true, 'r');
            }
            $cells[] = $this->c($fmt($s['pemakaian_pelumas_liter']), true, 'r');
            $cells[] = $this->c('', true);
            $cells[] = $this->c('', true);
            $push($cells);
        }

        return [
            'name' => 'Laporan Operasi',
            'cols' => $cols,
            'col_widths' => array_fill(0, $cols, 90),
            'merges' => $merges,
            'rows' => $rows,
        ];
    }

    /**
     * Render a grid (as saved from the spreadsheet editor) to an HTML table for
     * PDF export. Honours merges, bold and alignment.
     *
     * @param  array<string, mixed>  $grid
     */
    public function gridToHtml(array $grid): string
    {
        $rows = $grid['rows'] ?? [];
        $merges = $grid['merges'] ?? [];

        // Map every covered (non-origin) cell so it is skipped, and record the
        // span for each merge origin.
        $covered = [];
        $span = [];
        foreach ($merges as [$r1, $c1, $r2, $c2]) {
            $span["$r1:$c1"] = [$r2 - $r1 + 1, $c2 - $c1 + 1];
            for ($r = $r1; $r <= $r2; $r++) {
                for ($c = $c1; $c <= $c2; $c++) {
                    if ($r !== $r1 || $c !== $c1) {
                        $covered["$r:$c"] = true;
                    }
                }
            }
        }

        $html = '<table style="width:100%; border-collapse:collapse; font-size:11px;">';
        foreach ($rows as $r => $cells) {
            $html .= '<tr>';
            foreach ($cells as $c => $cell) {
                if (isset($covered["$r:$c"])) {
                    continue;
                }
                $attrs = '';
                if (isset($span["$r:$c"])) {
                    [$rowspan, $colspan] = $span["$r:$c"];
                    if ($rowspan > 1) {
                        $attrs .= ' rowspan="'.$rowspan.'"';
                    }
                    if ($colspan > 1) {
                        $attrs .= ' colspan="'.$colspan.'"';
                    }
                }
                $align = match ($cell['a'] ?? 'l') {
                    'c' => 'center',
                    'r' => 'right',
                    default => 'left',
                };
                $weight = ($cell['b'] ?? false) ? 'font-weight:bold;' : '';
                $style = "border:1px solid #000; padding:2px 4px; text-align:{$align}; {$weight}";
                $html .= '<td'.$attrs.' style="'.$style.'">'.e((string) ($cell['t'] ?? '')).'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</table>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function narrative(array $data, string $subject): string
    {
        $n = $data['narrative'];

        return "Pada Hari ini {$n['hari']} Tanggal {$n['tanggal_terbilang']} Bulan {$n['bulan']} "
            ."Tahun {$n['tahun_terbilang']} ({$n['tanggal_penuh']}) kami yang bertanda tangan di bawah ini "
            ."menyatakan bahwa telah diadakan pemeriksaan {$subject} pada {$data['unit']['name']} "
            .'dengan hasil sebagai berikut:';
    }

    /**
     * @return array{t: string, b?: bool, a?: string}
     */
    private function c(string $text, bool $bold = false, string $align = 'l'): array
    {
        $cell = ['t' => $text];
        if ($bold) {
            $cell['b'] = true;
        }
        if ($align !== 'l') {
            $cell['a'] = $align;
        }

        return $cell;
    }
}
