<?php

namespace App\Services\Reports;

use App\Models\ServiceUnit;
use App\Models\UlReportDocument;
use App\Models\UlReportStep;
use App\Models\User;
use App\Support\Indonesian;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;

/**
 * The editable document of a Laporan UL: its data ({@see UlReport} plus the
 * signers), the text body (Sampul, Daftar Isi, Lembar Pengesahan, one
 * landscape page group per bagian, Status & Kelengkapan), the styles, the
 * spreadsheet letterhead & grid, and the signature block refreshed inside a
 * saved document.
 */
class UlReportDocumentBuilder
{
    /** Bump when the body layout changes: a document saved on an older layout re-renders. */
    public const BODY_VERSION = 1;

    public function __construct(
        private readonly UlReport $report,
        private readonly UlReportWorkflow $workflow,
    ) {}

    /**
     * @return array{report: array<string, mixed>, document_number: string, tempat_tanggal: string, signatures: string}
     */
    public function build(string $kind, ServiceUnit $ul, int $month, int $year, ?UlReportDocument $document, ?User $user = null): array
    {
        return [
            'report' => $this->report->build($kind, $ul, $month, $year),
            'document_number' => self::documentNumber($kind, $ul, $month, $year),
            'tempat_tanggal' => 'Kendari, '.Indonesian::longDate(Carbon::create($year, $month, 1)->addMonth()),
            'signatures' => $this->signatures($ul, $kind, $document, $user),
        ];
    }

    public static function documentNumber(string $kind, ServiceUnit $ul, int $month, int $year): string
    {
        $code = strtoupper((string) ($ul->code ?: str($ul->name)->slug()));

        return sprintf('LAP-UL-%s/%s/%02d/%d', strtoupper($kind === 'pengusahaan' ? 'PGS' : ($kind === 'logistik' ? 'LOG' : 'K3L')), $code, $month, $year);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function bodyHtml(array $data): string
    {
        return View::make('reports.ul-document.body', ['data' => $data])->render();
    }

    public function contentStyles(): string
    {
        return View::make('reports.ul-document.styles')->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function letterhead(array $data): string
    {
        return View::make('reports.ul-document.letterhead', ['data' => $data])->render();
    }

    /**
     * The current signature block (Dibuat Oleh + approvers with their marks).
     */
    public function signatures(ServiceUnit $ul, string $kind, ?UlReportDocument $document, ?User $user = null): string
    {
        $maker = $document?->submitter?->name ?? ($user !== null && UlReport::allows($user) && ! $user->isSuperAdmin() ? $user->name : '');
        $format = fn (?CarbonInterface $at): ?string => $at?->timezone(config('app.timezone'))->format('d/m/Y H:i');

        $approvers = $document !== null && $document->steps->isNotEmpty()
            ? $document->steps->map(fn (UlReportStep $step): array => [
                'caption' => $step->caption,
                'position' => $step->position,
                'name' => (string) ($step->employee?->name ?? ''),
                'signed_at' => $format($step->signed_at),
            ])->values()->all()
            : array_map(fn (array $step): array => [
                'caption' => $step['caption'],
                'position' => $step['position']->value,
                'name' => (string) ($step['employee']?->name ?? ''),
                'signed_at' => null,
            ], $this->workflow->chain($ul, $kind));

        return View::make('reports.ul-document.signatures', ['signers' => [
            ['caption' => 'Dibuat Oleh', 'position' => 'Admin UL', 'name' => $maker, 'signed_at' => $format($document?->submitted_at)],
            ...$approvers,
        ]])->render();
    }

    /**
     * A saved document with its signature block brought up to date.
     */
    public function withCurrentSignatures(string $html, string $signatures): string
    {
        if (! str_contains($html, 'ul-ttd')) {
            return $html;
        }

        return preg_replace('/<table\b[^>]*\bid="ul-ttd"[^>]*>.*?<\/table>/s', $signatures, $html, 1) ?? $html;
    }

    /**
     * The spreadsheet mode: every table one under another (the charts stay in
     * the text / PDF mode).
     *
     * @param  array<string, mixed>  $data
     * @return array{name: string, cols: int, col_widths: list<int>, merges: list<array{0: int, 1: int, 2: int, 3: int}>, rows: list<list<array<string, mixed>>>}
     */
    public function grid(array $data): array
    {
        $report = $data['report'];
        $tables = [];
        foreach ($report['parts'] as $part) {
            if ($part['kpis'] !== []) {
                $tables[] = [
                    'title' => "{$part['title']} — Indikator Utama (Akumulasi UL)",
                    'columns' => ['Indikator', 'Bulan Ini', 'Bulan Lalu', 'Perubahan (%)'],
                    'rows' => array_map(fn (array $kpi): array => [$kpi['label'], $kpi['value'], $kpi['previous'], $kpi['delta'] === null ? '—' : number_format((float) $kpi['delta'], 1, ',', '.')], $part['kpis']),
                ];
            }
            if ($part['unit_table'] !== null) {
                $tables[] = $part['unit_table'];
            }
            foreach ($part['sections'] as $section) {
                $tables[] = [...$section, 'title' => "{$part['title']} — {$section['title']}"];
            }
        }
        $tables[] = $report['reports'];
        $tables[] = $report['completeness'];

        $width = max(6, ...array_map(fn (array $t): int => count($t['columns']), $tables));
        $rows = [];
        $merges = [];
        $full = function (string $text, bool $bold = false) use (&$rows, &$merges, $width): void {
            $rows[] = [$this->cell($text, $bold, $bold ? 'c' : 'l')];
            $merges[] = [count($rows) - 1, 0, count($rows) - 1, $width - 1];
        };

        $full(mb_strtoupper($report['ul']['name']), true);
        $full(mb_strtoupper($report['title']).' — '.mb_strtoupper($report['period']['label']), true);
        $rows[] = [$this->cell('')];

        foreach ($tables as $table) {
            $full($table['title'], true);
            $rows[] = array_map(fn (string $column): array => $this->cell($column, true, 'c'), $table['columns']);
            foreach ([...$table['rows'], ...(isset($table['footer']) ? [$table['footer']] : [])] as $row) {
                $rows[] = array_map(fn ($value): array => $this->cell((string) $value), $row);
            }
            $rows[] = [$this->cell('')];
        }

        return [
            'name' => mb_substr($report['title'], 0, 31),
            'cols' => $width,
            'col_widths' => [220, ...array_fill(0, $width - 1, 110)],
            'merges' => $merges,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{t: string, b?: bool, a?: string}
     */
    private function cell(string $text, bool $bold = false, string $align = 'l'): array
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
