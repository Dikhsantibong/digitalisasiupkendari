<?php

namespace App\Services\Har;

use App\Services\Reports\FragmentGridBuilder;

/**
 * Turns the computed HAR monthly report into a spreadsheet grid (cells + merges
 * + column widths) for the Excel edit mode. The letterhead (logo + kop) is added
 * at PDF/Excel export time — a spreadsheet cannot hold the logo image — so this
 * builds only the report body. Cell text is pre-formatted to read identically in
 * the spreadsheet, the .xlsx download, and the PDF.
 *
 * Grid shape: array{name, cols, col_widths, merges: list<array{0..3:int}>,
 *   rows: list<list<array{t:string, b?:bool, a?:string}>>}
 */
class HarDocumentGridBuilder
{
    private const COLS = 8;

    public function __construct(private readonly FragmentGridBuilder $fragments) {}

    /**
     * @param  array<string, mixed>  $data  the HarDocumentBuilder payload
     * @return array<string, mixed>
     */
    public function build(array $data): array
    {
        /** @var array<string, mixed> $report */
        $report = $data['report'];
        /** @var array<string, string> $numbers */
        $numbers = $data['document']['numbers'] ?? [];

        $rows = [];
        $merges = [];

        $push = function (array $row) use (&$rows): int {
            $rows[] = $row;

            return count($rows) - 1;
        };
        $full = function (int $r) use (&$merges): void {
            $merges[] = [$r, 0, $r, self::COLS - 1];
        };
        $title = function (string $text, ?string $number) use ($push, $full): void {
            $label = $number ? "{$text}  ({$number})" : $text;
            $full($push([$this->c($label, true, 'l')]));
        };

        // Header band.
        $full($push([$this->c((string) ($report['unit']['service_unit'] ?? 'UNIT LAYANAN'), true, 'c')]));
        $full($push([$this->c('LAPORAN PEMELIHARAAN (HAR) — '.$report['unit']['name'].' · '.$report['period']['label'], true, 'c')]));
        $push([$this->c('')]);

        // 1. SR Summary.
        $title('1. Service Request Summary', $numbers['sr_summary'] ?? null);
        $push([$this->c('Total SR', true), $this->c((string) $report['sr_summary']['total']), $this->c('Open', true), $this->c((string) $report['sr_summary']['open']), $this->c('Close', true), $this->c((string) $report['sr_summary']['close'])]);
        foreach ($report['sr_summary']['by_category'] as $cat) {
            $push([$this->c('Kategori '.$cat['category']), $this->c((string) $cat['count'])]);
        }
        $push([$this->c('')]);

        // 2. Maintenance Summary (FMKD-314-10.3.3-A9)
        if (isset($report['maintenance_summary'])) {
            $ms = $report['maintenance_summary'];
            $title('2. Maintenance Summary', $numbers['maintenance_summary'] ?? 'FMKD-314-10.3.3-A9');

            // 2.1 Rekap WO Terbit dan Complete
            $push([$this->c('2.1 Rekapitulasi WO Terbit dan Complete (192.168.3.85/wpc-ditgas)', true)]);
            $push([
                $this->c('BULAN', true, 'c'),
                $this->c('TERBIT', true, 'c'),
                $this->c('JAN', true, 'c'), $this->c('FEB', true, 'c'), $this->c('MAR', true, 'c'),
                $this->c('APR', true, 'c'), $this->c('MEI', true, 'c'), $this->c('JUN', true, 'c'),
                $this->c('JUL', true, 'c'), $this->c('AUG', true, 'c'), $this->c('SEP', true, 'c'),
                $this->c('OKT', true, 'c'), $this->c('NOV', true, 'c'), $this->c('DES', true, 'c'),
                $this->c('OPEN', true, 'c'),
            ]);
            foreach ($ms['rekap_terbit_complete']['rows'] ?? [] as $r) {
                $push([
                    $this->c((string) $r['bulan'], false, 'c'),
                    $this->c((string) $r['terbit'], false, 'c'),
                    $this->c((string) ($r['complete'][1] ?? 0), false, 'c'),
                    $this->c((string) ($r['complete'][2] ?? 0), false, 'c'),
                    $this->c((string) ($r['complete'][3] ?? 0), false, 'c'),
                    $this->c((string) ($r['complete'][4] ?? 0), false, 'c'),
                    $this->c((string) ($r['complete'][5] ?? 0), false, 'c'),
                    $this->c((string) ($r['complete'][6] ?? 0), false, 'c'),
                    $this->c((string) ($r['complete'][7] ?? 0), false, 'c'),
                    $this->c((string) ($r['complete'][8] ?? 0), false, 'c'),
                    $this->c((string) ($r['complete'][9] ?? 0), false, 'c'),
                    $this->c((string) ($r['complete'][10] ?? 0), false, 'c'),
                    $this->c((string) ($r['complete'][11] ?? 0), false, 'c'),
                    $this->c((string) ($r['complete'][12] ?? 0), false, 'c'),
                    $this->c((string) $r['open'], false, 'c'),
                ]);
            }
            $push([$this->c('')]);

            // 2.2 Rekapitulasi Status WO
            $push([$this->c('2.2 Rekapitulasi Status WO (192.168.3.85/wpc-ditgas)', true)]);
            $cols = $ms['rekap_status']['columns'] ?? ['CM', 'EM', 'WR', 'RTF', 'PM', 'PDM', 'EJ', 'PAM', 'CP', 'OH', 'ADM', 'OP', 'KOSONG'];
            $header2 = array_merge([$this->c('STATUS', true, 'c')], array_map(fn ($c) => $this->c($c, true, 'c'), $cols), [$this->c('TOTAL', true, 'c')]);
            $push($header2);
            foreach ($ms['rekap_status']['rows'] ?? [] as $sr) {
                $rowCells = [$this->c((string) $sr['status'], true, 'c')];
                foreach ($cols as $colName) {
                    $rowCells[] = $this->c((string) ($sr['values'][$colName] ?? 0), false, 'c');
                }
                $rowCells[] = $this->c((string) ($sr['total'] ?? 0), true, 'c');
                $push($rowCells);
            }
            $push([$this->c('')]);

            // 2.3 Penyelesaian Work Order Task
            $push([$this->c('2.3 Penyelesaian Work Order Task', true)]);
            $push([
                $this->c('NO', true, 'c'),
                $this->c('MAINTENANCE TYPE', true, 'l'),
                $this->c('RENCANA FREQ', true, 'c'),
                $this->c('RENCANA %', true, 'c'),
                $this->c('REALISASI FREQ', true, 'c'),
                $this->c('REALISASI %', true, 'c'),
            ]);
            foreach ($ms['tasks']['rows'] ?? [] as $tr) {
                $push([
                    $this->c((string) $tr['no'], false, 'c'),
                    $this->c((string) $tr['name'], false, 'l'),
                    $this->c((string) $tr['rencana_freq'], false, 'c'),
                    $this->c($tr['rencana_pct'].'%', false, 'c'),
                    $this->c((string) $tr['realisasi_freq'], false, 'c'),
                    $this->c($tr['realisasi_pct'].'%', false, 'c'),
                ]);
            }
            $push([$this->c('')]);
        }

        // 3. WO Summary.
        $title('3. Work Order Summary', $numbers['wo_summary'] ?? null);
        $push([$this->c('Total WO', true), $this->c((string) $report['wo_summary']['total']), $this->c('Complete', true), $this->c((string) $report['wo_summary']['complete']), $this->c('Open', true), $this->c((string) $report['wo_summary']['open']), $this->c('%', true), $this->c($report['wo_summary']['percent'].'%')]);
        $push([$this->c('')]);

        // 3. Rekap WO per jenis.
        $title('3. Rekapitulasi WO per Jenis', $numbers['wo_by_type'] ?? null);
        foreach ($report['wo_by_type'] as $group) {
            $typeUpper = strtoupper($group['type']);
            $isPm = $typeUpper === 'PM';
            $isPdm = in_array($typeUpper, ['PDM', 'PdM'], true);
            $isCm = in_array($typeUpper, ['CM', 'CORRECTIVE'], true);
            $gNumber = match (true) {
                $isPm => $numbers['wo_pm'] ?? 'FMKD-314-10.3.3-A12',
                $isPdm => $numbers['wo_pdm'] ?? 'FMKD-314-10.3.3-A13',
                $isCm => $numbers['wo_cm'] ?? 'FMKD-314-10.3.3-A14',
                default => null,
            };
            $gTitle = match (true) {
                $isPm => 'WO Preventive Maintenance',
                $isPdm => 'WO Predictive Maintenance',
                $isCm => 'WO Corrective Maintenance',
                default => 'Jenis: '.$group['type'],
            };
            if ($gNumber) {
                $title($gTitle, $gNumber);
            } else {
                $push([$this->c($gTitle, true)]);
            }
            $push([$this->c('NO', true, 'c'), $this->c('WONUM', true, 'c'), $this->c('DESCRIPTION', true, 'c'), $this->c('REPORT DATE', true, 'c'), $this->c('SCHED START', true, 'c'), $this->c('SCHED FINISH', true, 'c'), $this->c('STATUS', true, 'c'), $this->c('WORK GROUP', true, 'c')]);
            foreach ($group['rows'] as $idx => $r) {
                $push([
                    $this->c((string) ($idx + 1), false, 'c'),
                    $this->c((string) $r['wonum'], false, 'c'),
                    $this->c((string) ($r['description'] ?? '')),
                    $this->c((string) ($r['report_date'] ?? ''), false, 'c'),
                    $this->c((string) ($r['sched_start'] ?? ''), false, 'c'),
                    $this->c((string) ($r['sched_finish'] ?? ''), false, 'c'),
                    $this->c((string) ($r['status'] ?? ''), false, 'c'),
                    $this->c((string) ($r['work_group'] ?? ''), false, 'c'),
                ]);
            }
        }
        $push([$this->c('')]);

        // Rekapitulasi WO Task (FMKD-314-10.3.3-A11)
        if (isset($report['rekap_task_wo'])) {
            $rt = $report['rekap_task_wo'];
            $title('Rekapitulasi WO Task (Preventive, Proactive, Predictive, Corrective, Emergency, ECP)', $numbers['rekap_task_wo'] ?? 'FMKD-314-10.3.3-A11');
            $push([
                $this->c('NO', true, 'c'),
                $this->c('URAIAN', true, 'l'),
                $this->c('RENCANA [Freq]', true, 'c'),
                $this->c('RENCANA %', true, 'c'),
                $this->c('REALISASI FREK', true, 'c'),
                $this->c('REALISASI % Compliance', true, 'c'),
            ]);
            foreach ($rt['categories'] ?? [] as $cat) {
                $push([
                    $this->c((string) $cat['no'], true, 'c'),
                    $this->c((string) $cat['title'], true, 'l'),
                    $this->c((string) $cat['rencana_freq'], true, 'c'),
                    $this->c(($cat['rencana_pct'] > 0 ? number_format($cat['rencana_pct'], 1, ',', '.') : '0').'%', true, 'c'),
                    $this->c((string) $cat['realisasi_freq'], true, 'c'),
                    $this->c(($cat['realisasi_pct'] > 0 ? number_format($cat['realisasi_pct'], 1, ',', '.') : '0').'%', true, 'c'),
                ]);
                foreach ($cat['disciplines'] ?? [] as $d) {
                    $push([
                        $this->c('', false, 'c'),
                        $this->c('  '.$d['name'], false, 'l'),
                        $this->c((string) $d['rencana_freq'], false, 'c'),
                        $this->c(($d['rencana_pct'] > 0 ? number_format($d['rencana_pct'], 1, ',', '.') : '0').'%', false, 'c'),
                        $this->c((string) $d['realisasi_freq'], false, 'c'),
                        $this->c(($d['realisasi_pct'] > 0 ? number_format($d['realisasi_pct'], 1, ',', '.') : '0').'%', false, 'c'),
                    ]);
                }
            }
            $push([
                $this->c('TOTAL', true, 'c'),
                $this->c('', true, 'c'),
                $this->c((string) ($rt['total_rencana_freq'] ?? 0), true, 'c'),
                $this->c((($rt['total_rencana_pct'] ?? 0) > 0 ? number_format($rt['total_rencana_pct'], 1, ',', '.') : '0').'%', true, 'c'),
                $this->c((string) ($rt['total_realisasi_freq'] ?? 0), true, 'c'),
                $this->c((($rt['total_realisasi_pct'] ?? 0) > 0 ? number_format($rt['total_realisasi_pct'], 1, ',', '.') : '0').'%', true, 'c'),
            ]);
            $push([$this->c('')]);
        }

        // 4. WO tertunda.
        $title('4. WO Tertunda', null);
        foreach ($report['wo_waiting'] as $group) {
            $push([$this->c($group['reason'], true)]);
            foreach ($group['rows'] as $r) {
                $push([$this->c((string) $r['wonum']), $this->c((string) ($r['description'] ?? '')), $this->c((string) ($r['status'] ?? ''))]);
            }
        }
        $push([$this->c('')]);

        // 5. Rencana vs Realisasi.
        $title('5. Rencana vs Realisasi', $numbers['schedules'] ?? null);
        foreach ($report['schedules'] as $scope) {
            $push([$this->c($scope['scope'], true)]);
            $push([$this->c('Mesin', true, 'c'), $this->c('Rencana (tgl:kode)', true, 'c'), $this->c('Realisasi (tgl:kode)', true, 'c')]);
            foreach ($scope['rows'] as $r) {
                $push([$this->c((string) $r['engine']), $this->c($this->dayMap($r['rencana'])), $this->c($this->dayMap($r['realisasi']))]);
            }
        }
        $push([$this->c('')]);

        // 6. Log kegiatan HARMES.
        $title('6. Log Kegiatan HARMES', $numbers['activities'] ?? null);
        if ($report['activities'] !== []) {
            $push([$this->c('Tanggal', true, 'c'), $this->c('Mesin', true, 'c'), $this->c('Jenis', true, 'c'), $this->c('Uraian', true, 'c'), $this->c('Material', true, 'c'), $this->c('Hasil', true, 'c'), $this->c('No. WO/SR', true, 'c')]);
            foreach ($report['activities'] as $a) {
                $push([
                    $this->c((string) ($a['date'] ?? '')),
                    $this->c((string) ($a['engine'] ?? '')),
                    $this->c((string) ($a['type'] ?? '')),
                    $this->c(implode('; ', $a['tasks']) ?: (string) ($a['keterangan'] ?? '')),
                    $this->c($this->materials($a['materials'])),
                    $this->c((string) ($a['work_result'] ?? '')),
                    $this->c((string) ($a['no_wo'] ?? $a['no_sr'] ?? '')),
                ]);
            }
        }

        $grid = [
            'name' => 'Laporan HAR',
            'cols' => self::COLS,
            'col_widths' => [90, 220, 90, 90, 90, 90, 80, 80],
            'merges' => $merges,
            'rows' => $rows,
        ];

        return ($data['parts'] ?? []) === [] ? $grid : $this->withParts($grid, $data);
    }

    /**
     * Appends the embedded jadwal/formulir/input tables (see
     * HarDocumentBuilder::sources()) below the report body, cell by cell.
     *
     * @param  array<string, mixed>  $grid
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withParts(array $grid, array $data): array
    {
        $parts = $this->fragments->build([
            'document' => ['title' => 'LAMPIRAN JADWAL, FORMULIR & INPUT PEMELIHARAAN'],
            'report' => ['unit' => ['name' => $data['report']['unit']['name']], 'period' => ['label' => $data['report']['period']['label']]],
            'parts' => $data['parts'],
        ]);

        $offset = count($grid['rows']) + 1;
        $cols = max($grid['cols'], $parts['cols']);

        return [
            ...$grid,
            'cols' => $cols,
            'col_widths' => array_map(fn (int $i): int => $grid['col_widths'][$i] ?? 70, range(0, $cols - 1)),
            'merges' => [
                ...$grid['merges'],
                ...array_map(fn (array $m): array => [$m[0] + $offset, $m[1], $m[2] + $offset, $m[3]], $parts['merges']),
            ],
            'rows' => [...$grid['rows'], [$this->c('')], ...$parts['rows']],
        ];
    }

    /**
     * @param  array<string, string>  $map
     */
    private function dayMap(array $map): string
    {
        return collect($map)
            ->filter(fn ($v): bool => $v !== null && $v !== '')
            ->map(fn ($v, $d): string => "{$d}:{$v}")
            ->implode(' · ');
    }

    /**
     * @param  list<array<string, mixed>>  $materials
     */
    private function materials(array $materials): string
    {
        return collect($materials)
            ->map(fn (array $m): string => $m['name'].' ('.($m['quantity'] ?? '').($m['unit_of_measure'] ?? '').')')
            ->implode(', ');
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
