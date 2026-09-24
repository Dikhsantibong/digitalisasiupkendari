<?php

namespace App\Http\Controllers\Pdm;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\HandlesPdmInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\PdmSampleMonitoring;
use App\Models\PdmSampleMonitoringItem;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use App\Support\PdmInputKop;
use App\Support\PdmSampleMonitoringForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input PdM "Form Monitoring Pemeriksaan & Pengiriman Sample": header (unit/
 * lokasi, PIC, rekap targets, catatan) and the rows of sections A (pengiriman),
 * B (hasil analisis) and D (temuan); section C (rekap) is computed from A & B
 * by {@see PdmSampleMonitoringForm::rekap()}.
 */
class SampleMonitoringController extends Controller
{
    use HandlesPdmInput;
    use RendersReportPdf;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        [$units, $unit, $month, $year] = $this->pdmReadTarget($request);
        $document = $this->document($unit, $month, $year);

        return Inertia::render('pdm/input/sample-monitoring/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'kop_lines' => PdmInputKop::lines('sample-monitoring', $unit->name),
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => $this->pdmFilterOptions($units) + ['jenis_sample' => PdmSampleMonitoringForm::JENIS_SAMPLE],
            'sections' => collect(PdmSampleMonitoringForm::SECTIONS)
                ->map(fn (array $section, string $key): array => ['key' => $key, ...$section])->values()->all(),
            'document' => $document,
            'has_saved' => $document['id'] !== null,
            'can_write' => $request->user()->hasPermissionTo(PermissionName::PdmInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$unit, $month, $year] = $this->pdmWriteTarget($request);

        $validated = $request->validate([
            'lokasi' => ['nullable', 'string', 'max:255'],
            'pic_monitoring' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'targets' => ['nullable', 'array'],
            'targets.*.jenis' => ['required', 'string', Rule::in(PdmSampleMonitoringForm::JENIS_SAMPLE)],
            'targets.*.target' => ['nullable', 'integer', 'min:0'],
            'targets.*.keterangan' => ['nullable', 'string', 'max:255'],
            'rows' => ['present', 'array'],
            ...collect(PdmSampleMonitoringForm::SECTIONS)->mapWithKeys(fn (array $s, string $key): array => [
                "rows.{$key}" => ['nullable', 'array'],
                "rows.{$key}.*" => ['array'],
                "rows.{$key}.*.*" => ['nullable', 'string', 'max:500'],
            ])->all(),
        ]);

        DB::transaction(function () use ($validated, $unit, $month, $year, $request): void {
            $document = PdmSampleMonitoring::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'year' => $year, 'month' => $month],
                [
                    'lokasi' => $validated['lokasi'] ?? null,
                    'pic_monitoring' => $validated['pic_monitoring'] ?? null,
                    'catatan' => $validated['catatan'] ?? null,
                    'rekap_targets' => collect($validated['targets'] ?? [])->mapWithKeys(fn (array $t): array => [
                        $t['jenis'] => ['target' => $t['target'] ?? null, 'keterangan' => $t['keterangan'] ?? null],
                    ])->all(),
                    'input_by' => $request->user()->id,
                ],
            );

            $document->items()->delete();

            foreach (array_keys(PdmSampleMonitoringForm::SECTIONS) as $section) {
                $order = 0;
                foreach ($validated['rows'][$section] ?? [] as $row) {
                    $data = PdmSampleMonitoringForm::clean($section, $row);
                    if (PdmSampleMonitoringForm::isBlank($data)) {
                        continue;
                    }

                    PdmSampleMonitoringItem::query()->create([
                        'pdm_sample_monitoring_id' => $document->id,
                        'unit_id' => $unit->id,
                        'section' => $section,
                        'data' => $data,
                        'sort_order' => $order++,
                    ]);
                }
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Monitoring Pemeriksaan & Pengiriman Sample PdM {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Form Monitoring Sample PdM berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        [, $unit, $month, $year] = $this->pdmReadTarget($request);

        return response($this->renderPdf($unit, $month, $year), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.sprintf('Monitoring_Sample_PdM_%s_%02d_%d.pdf', str_replace(' ', '_', $unit->name), $month, $year).'"',
        ]);
    }

    /**
     * The PDF bytes (landscape).
     */
    private function renderPdf(Unit $unit, int $month, int $year): string
    {
        [$view, $data] = $this->pdfView($unit, $month, $year);

        return $this->renderReportPdf($view, $data, 'landscape');
    }

    /**
     * The PDF view and its data — shared by the PDF and the editable Laporan PdM document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        return ['pdm.input.sample-monitoring-pdf', [
            'unit' => $unit,
            'periodLabel' => Indonesian::monthName($month).' '.$year,
            'sections' => PdmSampleMonitoringForm::SECTIONS,
            'document' => $this->document($unit, $month, $year),
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * The period's form: header, section rows padded to the form's default row
     * count with blank rows, and the computed rekap.
     *
     * @return array{id: int|null, lokasi: string, pic_monitoring: string, catatan: string, targets: list<array{jenis: string, target: int|null, keterangan: string}>, rows: array<string, list<array<string, string|null>>>, rekap: list<array<string, mixed>>}
     */
    private function document(Unit $unit, int $month, int $year): array
    {
        $document = PdmSampleMonitoring::query()->with('items')
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->first();

        $rows = [];
        foreach (PdmSampleMonitoringForm::SECTIONS as $key => $section) {
            $saved = ($document?->items ?? collect())->where('section', $key)
                ->map(fn (PdmSampleMonitoringItem $item): array => PdmSampleMonitoringForm::clean($key, $item->data))
                ->values()->all();
            $blank = PdmSampleMonitoringForm::clean($key, []);
            $rows[$key] = array_merge($saved, array_fill(0, max(0, $section['default_rows'] - count($saved)), $blank));
        }

        $targets = $document?->rekap_targets ?? [];
        $filled = fn (string $key): array => array_values(array_filter($rows[$key], fn (array $row): bool => ! PdmSampleMonitoringForm::isBlank($row)));

        return [
            'id' => $document?->id,
            'lokasi' => (string) ($document?->lokasi ?? $unit->name),
            'pic_monitoring' => (string) ($document?->pic_monitoring ?? ''),
            'catatan' => (string) ($document?->catatan ?? ''),
            'targets' => array_map(fn (string $jenis): array => [
                'jenis' => $jenis,
                'target' => isset($targets[$jenis]['target']) ? (int) $targets[$jenis]['target'] : null,
                'keterangan' => (string) ($targets[$jenis]['keterangan'] ?? ''),
            ], PdmSampleMonitoringForm::JENIS_SAMPLE),
            'rows' => $rows,
            'rekap' => PdmSampleMonitoringForm::rekap($filled('pengiriman'), $filled('hasil'), $targets),
        ];
    }
}
