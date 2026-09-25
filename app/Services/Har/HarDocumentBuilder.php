<?php

namespace App\Services\Har;

use App\Enums\ReportModule;
use App\Http\Controllers\Har\DailyMeetingController;
use App\Http\Controllers\Har\LaporanGangguanController;
use App\Http\Controllers\Har\LembarController;
use App\Http\Controllers\Har\LogbookMutasiController;
use App\Http\Controllers\Har\PatrolCheckParameterController;
use App\Http\Controllers\Har\Program5s5rController;
use App\Http\Controllers\Har\TabelController;
use App\Models\HarLaporanGangguan;
use App\Models\HarLembarRow;
use App\Models\HarProgram5s5rItem;
use App\Models\HarTabelRow;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Reports\ReportWorkflowService;
use App\Services\Reports\ScopedHtmlFragment;
use App\Support\HarLembar\HarLembar;
use App\Support\HarLembar\HarLembars;
use App\Support\HarPatrolCheckParameter;
use App\Support\HarTabel\HarTabels;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Assembles the editable HAR monthly-report document: the letterhead metadata
 * (kop + ISO document number), the computed report payload, and the initial
 * rich-text body. The ISO numbers default from config/har.php and stay editable
 * inside the document itself (they are saved with the edited content).
 *
 * After the built-in jadwal sections, every HAR jadwal lembar, formulir
 * (Daily Meeting, Logbook Mutasi Harian, Laporan Gangguan LH-05) and input
 * table (Rekap Laporan Gangguan, Abnormal & Gangguan, Patrol Check, Patrol
 * Check Parameter Mesin, 5S5R) is
 * embedded from its own PDF view as a scoped fragment ("parts"), so the report
 * carries exactly the saved data in the same layout as the standalone PDF.
 */
class HarDocumentBuilder
{
    public function __construct(
        private readonly HarReportBuilder $reports,
        private readonly ReportWorkflowService $workflows,
        private readonly ScopedHtmlFragment $fragments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $report = $this->reports->monthly($unit, $month, $year);
        $numbers = (array) config('har.document.numbers', []);
        $signatories = $report['signatories'] ?? $this->reports->resolveSignatories($unit);

        return [
            'document' => [
                'number' => (string) ($numbers['report'] ?? ''),
                'title' => (string) config('har.document.title', 'LAPORAN PEMELIHARAAN (HAR)'),
                'revision' => (string) config('har.document.revision', '00'),
                'numbers' => $numbers,
                'signatories' => $signatories,
                'signature_blocks' => $this->workflows->signatureBlocks(ReportModule::Har, $unit, $month, $year),
            ],
            'report' => $report,
            ...$this->parts($unit, $month, $year),
        ];
    }

    /**
     * The initial rich-text body (letterhead + all report sections) shown in the
     * text editor and used for the text-mode PDF.
     *
     * @param  array<string, mixed>  $data
     */
    public function bodyHtml(array $data): string
    {
        return View::make('har.laporan.document-body', ['data' => $data])->render();
    }

    public function pengusahaanBodyHtml(array $data): string
    {
        return View::make('har.laporan.pengusahaan-body', ['data' => $data])->render();
    }

    /**
     * The letterhead banner shown above the spreadsheet editor and prepended to
     * the grid-mode PDF.
     *
     * @param  array<string, mixed>  $data
     */
    public function letterhead(array $data): string
    {
        return View::make('har.laporan.letterhead', ['data' => $data])->render();
    }

    /**
     * The report styles plus every embedded part's scoped styles.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function contentStyles(?array $data = null): string
    {
        return trim(View::make('har.laporan.styles')->render()."\n".($data['styles'] ?? ''));
    }

    /**
     * @return array{parts: list<array{group: string, key: string, title: string, orientation: string, saved: bool, scope: string, body: string}>, styles: string}
     */
    private function parts(Unit $unit, int $month, int $year): array
    {
        $parts = [];
        $styles = [];

        foreach ($this->sources($unit, $month, $year) as $source) {
            [$view, $data] = ($source['view'])();
            $scope = 'har-v-'.Str::slug(str_replace('.', '-', $view));
            $fragment = $this->fragments->extract(View::make($view, $data)->render(), $scope);
            $styles[$scope] = $fragment['css'];

            $parts[] = [
                'group' => $source['group'],
                'key' => $source['key'],
                'title' => $source['title'],
                'orientation' => $source['orientation'],
                'saved' => $source['saved'],
                'scope' => $scope,
                'body' => $fragment['body'],
            ];
        }

        return ['parts' => $parts, 'styles' => implode("\n", $styles)];
    }

    /**
     * Every embedded table of the report in Daftar Isi order. Per-machine
     * lembar print one table per machine with saved data, or the blank sheet
     * of the first machine when nothing is saved yet.
     *
     * @return list<array{group: string, key: string, title: string, orientation: string, saved: bool, view: \Closure(): array{0: string, 1: array<string, mixed>}}>
     */
    private function sources(Unit $unit, int $month, int $year): array
    {
        $sources = [];

        foreach (HarLembars::all() as $lembar) {
            if ($lembar->menu() === 'jadwal') {
                array_push($sources, ...$this->lembarSources($lembar, 'jadwal', $unit, $month, $year));
            }
        }

        $meetings = app(DailyMeetingController::class);
        $meetingRows = $meetings->meetings($unit, $month, $year);
        $sources[] = [
            'group' => 'formulir',
            'key' => 'daily-meeting',
            'title' => 'Formulir Daily Meeting Pemeliharaan',
            'orientation' => 'portrait',
            'saved' => $meetingRows->isNotEmpty(),
            'view' => fn (): array => $meetings->pdfView($unit, $meetingRows),
        ];

        $logbook = app(LogbookMutasiController::class);
        $logbookRows = $logbook->logbooks($unit, $month, $year);
        $sources[] = [
            'group' => 'formulir',
            'key' => 'logbook-mutasi',
            'title' => 'Logbook Mutasi Harian Tim Pemeliharaan',
            'orientation' => 'portrait',
            'saved' => $logbookRows->isNotEmpty(),
            'view' => fn (): array => $logbook->pdfView($unit, $logbookRows),
        ];

        $gangguan = app(LaporanGangguanController::class);
        $reports = $gangguan->reports($unit, $month, $year);
        $lh05 = $reports->isNotEmpty() ? $reports->all() : [new HarLaporanGangguan(['unit_id' => $unit->id, 'year' => $year])];
        foreach ($lh05 as $index => $report) {
            $sources[] = [
                'group' => 'formulir',
                'key' => 'laporan-gangguan-lh05'.($report->exists ? '-'.$report->id : ''),
                'title' => 'Formulir Laporan Gangguan (LH-05)'.(count($lh05) > 1 ? ' #'.($index + 1) : ''),
                'orientation' => 'portrait',
                'saved' => $report->exists,
                'view' => fn (): array => $gangguan->pdfView($unit, $report),
            ];
        }

        $tabels = app(TabelController::class);
        foreach (HarTabels::all() as $tabel) {
            $sources[] = [
                'group' => 'input',
                'key' => 'tabel-'.$tabel->key(),
                'title' => $tabel->title(),
                'orientation' => $tabel->orientation(),
                'saved' => HarTabelRow::query()->where(['unit_id' => $unit->id, 'tabel' => $tabel->key(), 'year' => $year, 'month' => $month])->exists(),
                'view' => fn (): array => $tabels->pdfView($tabel, $unit, $month, $year),
            ];
        }

        foreach (HarLembars::all() as $lembar) {
            if ($lembar->menu() === 'input') {
                array_push($sources, ...$this->lembarSources($lembar, 'input', $unit, $month, $year));
            }
        }

        // Patrol Check Parameter Mesin: one sheet per machine with readings
        // (the first machine's blank sheet before anything is saved).
        $parameters = app(PatrolCheckParameterController::class);
        $withReadings = $parameters->machinesWithReadings($unit, $month, $year);
        $parameterMachines = $withReadings->isNotEmpty() ? $withReadings->all() : [Machine::query()->where('unit_id', $unit->id)->orderBy('name')->first()];
        foreach ($parameterMachines as $machine) {
            $sources[] = [
                'group' => 'input',
                'key' => 'patrol-check-parameter'.($machine ? '-'.$machine->id : ''),
                'title' => HarPatrolCheckParameter::TITLE.($machine ? ' — '.$machine->name : ''),
                'orientation' => 'landscape',
                'saved' => $withReadings->isNotEmpty(),
                'view' => fn (): array => $parameters->pdfView($unit, $month, $year, $machine),
            ];
        }

        $sources[] = [
            'group' => 'input',
            'key' => 'program-5s5r',
            'title' => 'Program Kegiatan 5S5R Pemeliharaan',
            'orientation' => 'landscape',
            'saved' => HarProgram5s5rItem::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->exists(),
            'view' => fn (): array => app(Program5s5rController::class)->pdfView($unit, $month, $year),
        ];

        return $sources;
    }

    /**
     * @return list<array{group: string, key: string, title: string, orientation: string, saved: bool, view: \Closure(): array{0: string, 1: array<string, mixed>}}>
     */
    private function lembarSources(HarLembar $lembar, string $group, Unit $unit, int $month, int $year): array
    {
        $saved = fn () => HarLembarRow::query()->where('unit_id', $unit->id)->where('lembar', $lembar->key())
            ->where('year', $year)->where('month', $lembar->yearly() ? 0 : $month);
        $controller = app(LembarController::class);

        $machines = [null];
        if ($lembar->perMachine()) {
            $subjects = $saved()->distinct()->pluck('subject')->map(fn (string $id): int => (int) $id)->all();
            $machines = $subjects !== []
                ? Machine::query()->whereIn('id', $subjects)->orderBy('name')->get()->all()
                : [Machine::query()->where('unit_id', $unit->id)->orderBy('name')->first()];
        }

        return array_map(fn (?Machine $machine): array => [
            'group' => $group,
            'key' => 'lembar-'.$lembar->key().($machine ? '-'.$machine->id : ''),
            'title' => $lembar->title().($lembar->perMachine() && $machine ? ' — '.$machine->name : ''),
            'orientation' => $lembar->orientation(),
            'saved' => $machine === null ? $saved()->exists() : $saved()->where('subject', (string) $machine->id)->exists(),
            'view' => fn (): array => $controller->pdfView($lembar, $unit, $month, $year, $machine),
        ], $machines);
    }
}
