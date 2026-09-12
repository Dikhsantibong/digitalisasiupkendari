<?php

namespace App\Services\Har;

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
        $rupiah = fn ($v): string => 'Rp '.number_format((float) $v, 0, ',', '.');

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

        // 2. WO Summary.
        $title('2. Work Order Summary', $numbers['wo_summary'] ?? null);
        $push([$this->c('Total WO', true), $this->c((string) $report['wo_summary']['total']), $this->c('Complete', true), $this->c((string) $report['wo_summary']['complete']), $this->c('Open', true), $this->c((string) $report['wo_summary']['open']), $this->c('%', true), $this->c($report['wo_summary']['percent'].'%')]);
        $push([$this->c('')]);

        // 3. Rekap WO per jenis.
        $title('3. Rekapitulasi WO per Jenis', $numbers['wo_by_type'] ?? null);
        foreach ($report['wo_by_type'] as $group) {
            $push([$this->c('Jenis: '.$group['type'], true)]);
            $push([$this->c('WONUM', true, 'c'), $this->c('Deskripsi', true, 'c'), $this->c('Mesin', true, 'c'), $this->c('Report', true, 'c'), $this->c('Sched Start', true, 'c'), $this->c('Sched Finish', true, 'c'), $this->c('Status', true, 'c'), $this->c('Group', true, 'c')]);
            foreach ($group['rows'] as $r) {
                $push([
                    $this->c((string) $r['wonum']),
                    $this->c((string) ($r['description'] ?? '')),
                    $this->c((string) ($r['engine'] ?? '')),
                    $this->c((string) ($r['report_date'] ?? '')),
                    $this->c((string) ($r['sched_start'] ?? '')),
                    $this->c((string) ($r['sched_finish'] ?? '')),
                    $this->c((string) ($r['status'] ?? '')),
                    $this->c((string) ($r['work_group'] ?? '')),
                ]);
            }
        }
        $push([$this->c('')]);

        // 4. WO tertunda.
        $title('4. WO Tertunda', null);
        foreach ($report['wo_waiting'] as $group) {
            $push([$this->c($group['reason'], true)]);
            foreach ($group['rows'] as $r) {
                $push([$this->c((string) $r['wonum']), $this->c((string) ($r['description'] ?? '')), $this->c((string) ($r['status'] ?? ''))]);
            }
        }
        $push([$this->c('')]);

        // 5. Akumulasi biaya.
        $title('5. Akumulasi Biaya Pemeliharaan', $numbers['cost'] ?? null);
        $push([$this->c('Jasa (WO)', true), $this->c($rupiah($report['cost']['auto_service']), false, 'r'), $this->c('Material (WO)', true), $this->c($rupiah($report['cost']['auto_material']), false, 'r')]);
        $push([$this->c('Efektif ('.$report['cost']['source'].')', true), $this->c($rupiah($report['cost']['effective_total']), false, 'r'), $this->c('Akumulasi YTD', true), $this->c($rupiah($report['cost']['ytd']), false, 'r')]);
        $push([$this->c('')]);

        // 6. Rencana vs Realisasi.
        $title('6. Rencana vs Realisasi', $numbers['schedules'] ?? null);
        foreach ($report['schedules'] as $scope) {
            $push([$this->c($scope['scope'], true)]);
            $push([$this->c('Mesin', true, 'c'), $this->c('Rencana (tgl:kode)', true, 'c'), $this->c('Realisasi (tgl:kode)', true, 'c')]);
            foreach ($scope['rows'] as $r) {
                $push([$this->c((string) $r['engine']), $this->c($this->dayMap($r['rencana'])), $this->c($this->dayMap($r['realisasi']))]);
            }
        }
        $push([$this->c('')]);

        // 7. Log kegiatan HARMES.
        $title('7. Log Kegiatan HARMES', $numbers['activities'] ?? null);
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

        return [
            'name' => 'Laporan HAR',
            'cols' => self::COLS,
            'col_widths' => [90, 220, 90, 90, 90, 90, 80, 80],
            'merges' => $merges,
            'rows' => $rows,
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
