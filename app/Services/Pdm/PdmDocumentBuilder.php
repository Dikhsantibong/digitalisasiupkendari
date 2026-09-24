<?php

namespace App\Services\Pdm;

use App\Enums\ReportModule;
use App\Http\Controllers\Pdm\FormInputController;
use App\Http\Controllers\Pdm\JadwalHarianController;
use App\Http\Controllers\Pdm\JadwalMeetingController;
use App\Http\Controllers\Pdm\JadwalPatrolCheckController;
use App\Http\Controllers\Pdm\KesiapanApdController;
use App\Http\Controllers\Pdm\PermitToWorkController;
use App\Http\Controllers\Pdm\Program5s5rController;
use App\Http\Controllers\Pdm\RealisasiPrediktifController;
use App\Http\Controllers\Pdm\SampleMonitoringController;
use App\Models\Machine;
use App\Models\PdmFormDocument;
use App\Models\PdmJadwal5s5r;
use App\Models\PdmJadwalHarian;
use App\Models\PdmJadwalMeeting;
use App\Models\PdmJadwalPatrolCheck;
use App\Models\PdmKesiapanApd;
use App\Models\PdmPermitToWork;
use App\Models\PdmRealisasiPrediktif;
use App\Models\PdmSampleMonitoring;
use App\Models\Unit;
use App\Services\K3\K3DocumentBuilder;
use App\Services\Reports\ReportWorkflowService;
use App\Services\Reports\ScopedHtmlFragment;
use App\Support\Indonesian;
use App\Support\PdmForms\PdmForms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Assembles the editable Laporan PdM & Maturity Level Pembangkit: Sampul,
 * Daftar Isi, Lembar Pengesahan, then every jadwal (resources/views/pdm/jadwal)
 * and every input table (resources/views/pdm/input). Each table is its own PDF
 * view rendered with exactly the data its page shows — saved rows, or the
 * page's default rows before anything is saved — and embedded as a scoped
 * fragment, so the report always carries the full table and stays editable.
 * Mirrors {@see K3DocumentBuilder}.
 */
class PdmDocumentBuilder
{
    public const TITLE = 'LAPORAN PREDICTIVE DAN MATURITY LEVEL PEMBANGKIT';

    /**
     * Generic forms listed after Realisasi Pemeliharaan Prediktif in the Input menu.
     */
    private const FORMS_AFTER_REALISASI = ['patrol-check-pdm', 'checklist-patrol-check'];

    public function __construct(
        private readonly ScopedHtmlFragment $fragments,
        private readonly ReportWorkflowService $workflows,
    ) {}

    /**
     * @return array{document: array{number: string, title: string, grid_name: string}, report: array<string, mixed>, parts: list<array{group: string, key: string, title: string, orientation: string, saved: bool, scope: string, body: string}>, styles: string}
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $parts = [];
        $styles = [];

        foreach ($this->sources($unit, $month, $year) as $source) {
            [$view, $data] = ($source['view'])();
            $scope = 'pdm-v-'.Str::slug(str_replace('.', '-', $view));
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

        return [
            'document' => [
                'number' => sprintf('LAP-PDM/%s/%02d/%d', Str::upper(Str::slug($unit->name)), $month, $year),
                'title' => self::TITLE,
                'grid_name' => 'Laporan PdM',
            ],
            'report' => [
                'unit' => ['id' => $unit->id, 'name' => $unit->name],
                'period' => ['month' => $month, 'year' => $year, 'label' => Indonesian::monthName($month).' '.$year],
                'pengesahan' => $this->pengesahan($unit, $month, $year),
            ],
            'parts' => $parts,
            'styles' => implode("\n", $styles),
        ];
    }

    /**
     * The report's tables in Daftar Isi order with whether their data is saved
     * for the period — without rendering them (menu Laporan overview).
     *
     * @return list<array{group: string, key: string, title: string, orientation: string, saved: bool}>
     */
    public function contents(Unit $unit, int $month, int $year): array
    {
        return array_map(
            fn (array $source): array => array_diff_key($source, ['view' => true]),
            $this->sources($unit, $month, $year),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function bodyHtml(array $data): string
    {
        return View::make('pdm.laporan.document-body', ['data' => $data])->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function letterhead(array $data): string
    {
        return View::make('pdm.laporan.letterhead', ['data' => $data])->render();
    }

    /**
     * The report styles plus every embedded table's scoped styles.
     *
     * @param  array<string, mixed>  $data
     */
    public function contentStyles(array $data): string
    {
        return View::make('pdm.laporan.styles')->render()."\n".$data['styles'];
    }

    /**
     * Lembar Pengesahan (Memeriksa Koordinator PDM · Menyetujui TL
     * Pemeliharaan · Mengesahkan Manager UL) and the in-report block
     * (Koordinator Pemeliharaan · PIC PDM) of the unit, from the report workflow.
     *
     * @return array{tempat_tanggal: string, blocks: array{pengesahan: string, laporan: string}}
     */
    public function pengesahan(Unit $unit, int $month, int $year): array
    {
        return [
            'tempat_tanggal' => (trim((string) $unit->location) ?: 'Kendari').', '.Indonesian::longDate(Carbon::create($year, $month, 1)->addMonth()),
            'blocks' => $this->workflows->signatureBlocks(ReportModule::Pdm, $unit, $month, $year),
        ];
    }

    /**
     * Every table of the report in Daftar Isi order. Per-machine forms print
     * one table per machine with saved data, or the blank form for the first
     * machine when none is saved yet.
     *
     * @return list<array{group: string, key: string, title: string, orientation: string, saved: bool, view: \Closure(): array{0: string, 1: array<string, mixed>}}>
     */
    private function sources(Unit $unit, int $month, int $year): array
    {
        $saved = fn (string $model): bool => $model::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->exists();
        $view = fn (string $controller): \Closure => fn (): array => app($controller)->pdfView($unit, $month, $year);

        $sources = [
            ['group' => 'jadwal', 'key' => 'jadwal-harian', 'title' => 'Jadwal Kegiatan Harian PdM Pembangkit', 'orientation' => 'landscape', 'saved' => $saved(PdmJadwalHarian::class), 'view' => $view(JadwalHarianController::class)],
            ['group' => 'jadwal', 'key' => 'jadwal-patrol-check', 'title' => 'Jadwal Piket Patrol Check PdM', 'orientation' => 'landscape', 'saved' => $saved(PdmJadwalPatrolCheck::class), 'view' => $view(JadwalPatrolCheckController::class)],
            ['group' => 'jadwal', 'key' => 'jadwal-5s5r', 'title' => 'Jadwal Program 5S 5R PdM', 'orientation' => 'landscape', 'saved' => $saved(PdmJadwal5s5r::class), 'view' => $view(Program5s5rController::class)],
            ['group' => 'jadwal', 'key' => 'jadwal-meeting', 'title' => 'Jadwal Meeting PdM KIT', 'orientation' => 'landscape', 'saved' => $saved(PdmJadwalMeeting::class), 'view' => $view(JadwalMeetingController::class)],
            ['group' => 'input', 'key' => 'kesiapan-apd', 'title' => 'Kesiapan APD Bagian PdM Pembangkit', 'orientation' => 'landscape', 'saved' => $saved(PdmKesiapanApd::class), 'view' => $view(KesiapanApdController::class)],
            ['group' => 'input', 'key' => 'sample-monitoring', 'title' => 'Form Monitoring Pemeriksaan & Pengiriman Sample PdM', 'orientation' => 'landscape', 'saved' => $saved(PdmSampleMonitoring::class), 'view' => $view(SampleMonitoringController::class)],
            ['group' => 'input', 'key' => 'permit-to-work', 'title' => 'Laporan Permit to Work (PTW) Pembangkit', 'orientation' => 'portrait', 'saved' => $saved(PdmPermitToWork::class), 'view' => $view(PermitToWorkController::class)],
        ];

        $forms = app(FormInputController::class);
        $formSources = [];
        foreach (PdmForms::all() as $form) {
            $documents = PdmFormDocument::query()->where('unit_id', $unit->id)->where('form', $form->key())
                ->where('year', $year)->where('month', $month)->orderBy('subject')->get(['subject']);

            $machines = [null];
            if ($form->perMachine()) {
                $machines = $documents->isNotEmpty()
                    ? Machine::query()->whereIn('id', $documents->pluck('subject')->map(fn (string $id): int => (int) $id))->orderBy('name')->get()->all()
                    : [Machine::query()->where('unit_id', $unit->id)->orderBy('name')->first()];
            }

            foreach ($machines as $machine) {
                $formSources[$form->key()][] = [
                    'group' => 'input',
                    'key' => $form->key().($machine ? '-'.$machine->id : ''),
                    'title' => $form->title().($form->perMachine() && $machine ? ' — '.$machine->name : ''),
                    'orientation' => $form->orientation(),
                    'saved' => $documents->isNotEmpty(),
                    'view' => fn (): array => $forms->pdfView($form, $unit, $month, $year, $machine),
                ];
            }
        }

        // Same order as the Input PdM menu: Realisasi Prediktif sits between
        // the other generic forms and the patrol check forms.
        $afterRealisasi = array_intersect_key($formSources, array_flip(self::FORMS_AFTER_REALISASI));
        $beforeRealisasi = array_diff_key($formSources, $afterRealisasi);

        return [
            ...$sources,
            ...array_merge(...array_values($beforeRealisasi ?: [[]])),
            ['group' => 'input', 'key' => 'realisasi-prediktif', 'title' => 'Realisasi Pemeliharaan Prediktif Bulanan', 'orientation' => 'landscape', 'saved' => $saved(PdmRealisasiPrediktif::class), 'view' => $view(RealisasiPrediktifController::class)],
            ...array_merge(...array_values($afterRealisasi ?: [[]])),
        ];
    }
}
