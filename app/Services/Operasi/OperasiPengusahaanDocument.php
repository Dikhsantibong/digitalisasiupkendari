<?php

namespace App\Services\Operasi;

use App\Enums\EmployeePosition;
use App\Models\Unit;
use App\Services\Reports\ReportSignatories;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;

/**
 * The Laporan Pengusahaan Pembangkit (Operasi) document: its data (unit,
 * period, signers and the {@see OperasiPengusahaanReport} sections), the text
 * body, styles, letterhead and the spreadsheet grid — the same layout as the
 * Laporan Pengusahaan K3 (shared cover & section partials).
 */
class OperasiPengusahaanDocument
{
    public function __construct(
        private readonly OperasiPengusahaanReport $report,
        private readonly ReportSignatories $signatories,
    ) {}

    /**
     * @return array{document: array{number: string, title: string}, report: array<string, mixed>, pengusahaan: list<array<string, mixed>>}
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $unit->loadMissing('serviceUnit');
        $signer = fn (EmployeePosition $position): array => [
            'name' => (string) ($this->signatories->holder($unit, $position)?->name ?? ''),
            'position' => $position->value,
        ];

        return [
            'document' => [
                'number' => OperasiPengusahaanReport::documentNumber($unit, $month, $year),
                'title' => 'LAPORAN PENGUSAHAAN PEMBANGKIT',
            ],
            'report' => [
                'unit' => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'service_unit' => $unit->serviceUnit?->name ?? 'UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI',
                ],
                'period' => [
                    'month' => $month,
                    'year' => $year,
                    'label' => Indonesian::monthName($month).' '.$year,
                    'month_name' => Indonesian::monthName($month),
                    'formatted_date' => Carbon::create($year, $month, 1)->endOfMonth()->day.' '.Indonesian::monthName($month).' '.$year,
                ],
                'signatories' => [
                    'staf' => ['name' => '', 'position' => 'Staf Operasi'],
                    'tl' => $signer(EmployeePosition::TeamLeaderOperasi),
                    'manager' => $signer(EmployeePosition::ManagerUl),
                ],
            ],
            'pengusahaan' => $this->report->sections($unit, $month, $year),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function bodyHtml(array $data): string
    {
        return View::make('operasi.laporan.pengusahaan-body', ['data' => $data])->render();
    }

    public function styles(): string
    {
        return View::make('operasi.laporan.pengusahaan-styles')->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function letterhead(array $data): string
    {
        return View::make('operasi.laporan.partials.letterhead', [
            'report' => $data['report'],
            'title' => $data['document']['title'],
        ])->render();
    }

    /**
     * The spreadsheet mode: every section one under another, header & body
     * cells placed around row/col spans (same as the K3 pengusahaan grid).
     *
     * @param  array<string, mixed>  $data
     * @return array{name: string, cols: int, col_widths: list<int>, merges: list<array{0: int, 1: int, 2: int, 3: int}>, rows: list<list<array<string, mixed>>>}
     */
    public function grid(array $data): array
    {
        $report = $data['report'];
        $sections = $data['pengusahaan'];
        $rows = [];
        $merges = [];
        $width = 6;
        foreach ($sections as $section) {
            foreach ([...$section['head'], ...$section['rows']] as $line) {
                $width = max($width, (int) array_sum(array_map(fn (array $cell): int => (int) ($cell['c'] ?? 1), $line)));
            }
        }

        $full = function (array $cell) use (&$rows, &$merges, $width): void {
            $rows[] = [$cell];
            $merges[] = [count($rows) - 1, 0, count($rows) - 1, $width - 1];
        };

        $full($this->c((string) $report['unit']['service_unit'], true, 'c'));
        $full($this->c('LAPORAN PENGUSAHAAN PEMBANGKIT (OPERASI) — '.$report['unit']['name'].' · '.$report['period']['label'], true, 'c'));
        $rows[] = [$this->c('')];

        foreach ($sections as $section) {
            $full($this->c("{$section['no']}. {$section['title']}", true));

            $taken = [];
            $lines = [...array_map(fn (array $l): array => [$l, true], $section['head']), ...array_map(fn (array $l): array => [$l, false], $section['rows'])];
            foreach ($lines as $offset => [$line, $isHead]) {
                $r = count($rows);
                $row = [];
                $col = 0;
                foreach ($line as $cell) {
                    while (isset($taken[$offset][$col])) {
                        $row[$col] = $this->c('');
                        $col++;
                    }
                    $span = (int) ($cell['c'] ?? 1);
                    $down = (int) ($cell['r'] ?? 1);
                    $row[$col] = $this->c((string) ($cell['t'] ?? ''), $isHead || ! empty($cell['b']), $isHead ? 'c' : (string) ($cell['a'] ?? 'l'));
                    for ($x = 1; $x < $span; $x++) {
                        $row[$col + $x] = $this->c('');
                    }
                    for ($y = 1; $y < $down; $y++) {
                        for ($x = 0; $x < $span; $x++) {
                            $taken[$offset + $y][$col + $x] = true;
                        }
                    }
                    if ($span > 1 || $down > 1) {
                        $merges[] = [$r, $col, $r + $down - 1, $col + $span - 1];
                    }
                    $col += $span;
                }
                while (isset($taken[$offset][$col])) {
                    $row[$col] = $this->c('');
                    $col++;
                }
                ksort($row);
                $rows[] = array_values($row);
            }

            if ($section['rows'] === []) {
                $full($this->c('Belum diisi pada periode ini.'));
            }
            if ($section['note'] !== null) {
                $full($this->c('Catatan: '.$section['note']));
            }
            $rows[] = [$this->c('')];
        }

        return [
            'name' => 'Laporan Pengusahaan Operasi',
            'cols' => $width,
            'col_widths' => [40, 200, ...array_fill(0, max(0, $width - 2), 80)],
            'merges' => $merges,
            'rows' => $rows,
        ];
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
