<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Concerns\InteractsWithReportWorkflow;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\OperasiReportDocument;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Operasi\DocumentGridBuilder;
use App\Services\Operasi\OperasiReportSections;
use App\Services\Operasi\OperasiReportTables;
use App\Services\Operasi\Reports\OperasiReport;
use App\Services\Operasi\Reports\ReportRegistry;
use App\Services\Reports\OrientationPdfMerger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The editable Operasi report document — same feature as Berita Acara & the HAR
 * report: the report is generated with its figures filled in, opened in a
 * Word-like editor (or an Excel-like grid), saved as editable HTML/grid, and
 * exported to PDF (dompdf) from exactly that edited content.
 */
class LaporanDocumentController extends Controller
{
    use EmbedsReportLogo;
    use InteractsWithReportWorkflow;
    use RendersReportPdf;

    /**
     * Current report-body template version. Bump when the layout changes so
     * documents saved against an older layout re-render from the new template.
     * v2 = full framework (cover, exec summary, daftar isi, istilah) + one
     * section per page; v3 = corporate polish (footer + page numbers, ToC
     * leaders, landscape wide tables); v4 = redesigned corporate cover matching MKP+PLN branding;
     * v5 = Daftar Isi I–V structure, one `.op-section` per point (tables landscape,
     *      sampul/daftar isi/resume/lampiran portrait), red line when a point has
     *      no data, Daftar Isi page numbers filled at export, cover unnumbered;
     * v6 = Lembar Pengesahan, Resume Statistik with 3D charts, and every jadwal /
     *      input point embedding its own PDF view (OperasiReportTables);
     * v7 = Lembar Pengesahan & tanda tangan laporan from the report workflow.
     */
    private const BODY_VERSION = 7;

    private const FOOTER = 'PT PLN NUSANTARA POWER UP KENDARI - LAPORAN OPERASI PEMBANGKIT';

    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly DocumentGridBuilder $gridBuilder,
        private readonly OperasiReportSections $sections,
        private readonly OperasiReportTables $tables,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /** @var array<string, array<string, mixed>> OperasiReportTables payload per unit & period, built once per request */
    private array $builtTables = [];

    public function edit(Request $request, string $report): Response
    {
        $user = $request->user();
        $this->authorizeReportView($request, ReportModule::Operasi, PermissionName::OperasiLaporanView);

        [$definition, $unit, $engine, $month, $year] = $this->resolveTarget($request, $report);

        $data = $definition->build($unit, $month, $year, $engine);
        $documentNumber = $this->documentNumber($unit, $month, $year);
        $record = $this->currentRecord($definition, $unit->id, $engine?->id, $month, $year);

        $grid = $record?->content_grid ?? $this->gridBuilder->forMonthlyReport($data);
        $letterhead = $this->letterhead($definition, $data);

        $workflow = $this->reportWorkflows()->present($user, ReportModule::Operasi, $unit, $month, $year);

        return Inertia::render('operasi/laporan/document', [
            'report' => ['code' => $definition->code(), 'title' => $definition->title()],
            'filters' => [
                'unit_id' => $unit->id,
                'engine_id' => $engine?->id,
                'month' => $month,
                'year' => $year,
            ],
            'document_number' => $documentNumber,
            'content' => $record?->content_html !== null
                ? $this->withCurrentSignatures($record->content_html, ReportModule::Operasi, $unit, $month, $year)
                : $this->bodyHtml($definition, $data, $documentNumber, $unit, $month, $year),
            'content_styles' => $this->styles($unit, $month, $year, $data),
            'letterhead' => $letterhead,
            'grid' => $grid,
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('operasi.laporan.document.pdf', [
                'report' => $definition->code(),
                'unit_id' => $unit->id,
                'engine_id' => $engine?->id,
                'month' => $month,
                'year' => $year,
            ]),
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiLaporanView) && $workflow['editable'],
            'workflow' => $workflow,
        ]);
    }

    public function pdf(Request $request, string $report, OrientationPdfMerger $merger)
    {
        $user = $request->user();
        $this->authorizeReportView($request, ReportModule::Operasi, PermissionName::OperasiLaporanView);

        [$definition, $unit, $engine, $month, $year] = $this->resolveTarget($request, $report);

        $data = $definition->build($unit, $month, $year, $engine);
        $record = $this->currentRecord($definition, $unit->id, $engine?->id, $month, $year);

        $filename = "Laporan-{$definition->code()}-{$unit->id}-{$month}-{$year}.pdf";

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            // Excel mode: the letterhead (logo + kop) is added here — a
            // spreadsheet cannot hold it — above the edited grid body.
            return $this->streamReportPdf(
                $request,
                'operasi.laporan.pdf-shell',
                ['content' => $this->embedAssets($this->letterhead($definition, $data).$this->gridBuilder->gridToHtml($record->content_grid))],
                $filename,
            );
        }

        // Text mode: every Daftar Isi point prints in its own orientation and the
        // portrait & landscape pages are merged into one PDF.
        $content = $record?->content_html !== null
            ? $this->withCurrentSignatures($record->content_html, ReportModule::Operasi, $unit, $month, $year)
            : $this->bodyHtml($definition, $data, $this->documentNumber($unit, $month, $year), $unit, $month, $year);

        $pdf = $merger->renderSections(
            $this->styles($unit, $month, $year, $data),
            $this->embedAssets($content),
            'op-section',
            'op-landscape',
            self::FOOTER,
            unnumberedPages: 1,
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    public function store(Request $request, string $report): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        [$definition, $unit, $engine, $month, $year] = $this->resolveTarget($request, $report);
        $this->ensureReportEditable(ReportModule::Operasi, $unit, $month, $year);

        $validated = $request->validate([
            'format' => ['required', Rule::in(['html', 'grid'])],
            'content_html' => ['required_if:format,html', 'nullable', 'string'],
            'content_grid' => ['required_if:format,grid', 'nullable', 'array'],
        ]);

        $data = $definition->build($unit, $month, $year, $engine);

        $record = OperasiReportDocument::query()->firstOrNew([
            'unit_id' => $unit->id,
            'report_code' => $definition->code(),
            'engine_id' => $engine?->id,
            'month' => $month,
            'year' => $year,
        ]);
        if (! $record->exists) {
            $record->created_by = $user->id;
        }
        $record->document_number = $this->documentNumber($unit, $month, $year);
        $record->format = $validated['format'];
        $record->content_version = self::BODY_VERSION;
        if (($validated['content_html'] ?? null) !== null) {
            $record->content_html = $validated['content_html'];
        }
        if (($validated['content_grid'] ?? null) !== null) {
            $record->content_grid = $validated['content_grid'];
        }
        $record->snapshot = $data;
        $record->save();

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menyimpan Dokumen {$definition->title()} {$unit->name} periode {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen laporan disimpan.']);

        return back();
    }

    /**
     * Rebuild the saved document from the latest data & template, discarding any
     * manual edits — so an improved report layout reaches a document that was
     * already saved with the old layout.
     */
    public function regenerate(Request $request, string $report): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        [$definition, $unit, $engine, $month, $year] = $this->resolveTarget($request, $report);
        $this->ensureReportEditable(ReportModule::Operasi, $unit, $month, $year);

        $data = $definition->build($unit, $month, $year, $engine);
        $grid = $this->gridBuilder->forMonthlyReport($data);

        $record = OperasiReportDocument::query()->firstOrNew([
            'unit_id' => $unit->id,
            'report_code' => $definition->code(),
            'engine_id' => $engine?->id,
            'month' => $month,
            'year' => $year,
        ]);
        if (! $record->exists) {
            $record->created_by = $user->id;
        }
        $record->document_number = $this->documentNumber($unit, $month, $year);
        $record->format = 'html';
        $record->content_version = self::BODY_VERSION;
        $record->content_html = $this->bodyHtml($definition, $data, $this->documentNumber($unit, $month, $year), $unit, $month, $year);
        $record->content_grid = $grid;
        $record->snapshot = $data;
        $record->save();

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Memuat ulang Dokumen {$definition->title()} {$unit->name} periode {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen dimuat ulang dari data terbaru.']);

        return back();
    }

    /**
     * Resolve and authorise the report/unit/engine/period from the request.
     *
     * @return array{0: OperasiReport, 1: Unit, 2: Machine|null, 3: int, 4: int}
     */
    private function resolveTarget(Request $request, string $report): array
    {
        $definition = $this->registry->find($report);
        abort_if($definition === null, 404);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($request->user()->canAccessUnit($unit), 403);

        $engine = null;
        if ($definition->requiresEngine() || ($request->filled('engine_id') && $request->integer('engine_id') > 0)) {
            $engine = Machine::query()->where('unit_id', $unit->id)->find($request->integer('engine_id'));
        }

        $month = (int) $request->integer('month');
        $year = (int) $request->integer('year');
        abort_unless($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100, 404);

        return [$definition, $unit, $engine, $month, $year];
    }

    private function existingRecord(OperasiReport $definition, int $unitId, ?int $engineId, int $month, int $year): ?OperasiReportDocument
    {
        return OperasiReportDocument::query()
            ->where('unit_id', $unitId)
            ->where('report_code', $definition->code())
            ->where('engine_id', $engineId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
    }

    /**
     * The saved document only when built from the current template version; a
     * stale one is ignored so the fresh template renders instead.
     */
    private function currentRecord(OperasiReport $definition, int $unitId, ?int $engineId, int $month, int $year): ?OperasiReportDocument
    {
        $record = $this->existingRecord($definition, $unitId, $engineId, $month, $year);

        return $record !== null && (int) $record->content_version === self::BODY_VERSION ? $record : null;
    }

    private function documentNumber(Unit $unit, int $month, int $year): string
    {
        return sprintf('LAP-OPS/%s/%02d-%d', $unit->code ?? $unit->id, $month, $year);
    }

    /**
     * The full report body in Daftar Isi order (sampul, daftar isi, resume
     * statistik, every Laporan Operasi point, lampiran) — the default
     * text-mode content and PDF.
     *
     * @param  array<string, mixed>  $data
     */
    private function bodyHtml(OperasiReport $definition, array $data, string $documentNumber, Unit $unit, int $month, int $year): string
    {
        return view('operasi.laporan.document-body', [
            'report' => $data,
            'sections' => $this->sections->build($unit, $month, $year, $data),
            'tables' => $this->tables($unit, $month, $year, $data),
            'pengesahan' => $this->tables->pengesahan($unit, $month, $year),
            'documentNumber' => $documentNumber,
            'reportTitle' => $definition->title(),
        ])->render();
    }

    /**
     * The embedded jadwal / input tables and the resume statistik.
     *
     * @param  array<string, mixed>  $data  the MonthlyEngineReport payload
     * @return array<string, mixed>
     */
    private function tables(Unit $unit, int $month, int $year, array $data): array
    {
        $engineDaysFilled = collect($data['rows'] ?? [])
            ->filter(fn (array $row): bool => ($row['kwh_produksi_stand_akhir'] ?? null) !== null)
            ->count();

        return $this->builtTables["{$unit->id}-{$month}-{$year}"] ??= $this->tables->build($unit, $month, $year, $engineDaysFilled);
    }

    /**
     * The report styles plus the scoped styles of every embedded table.
     *
     * @param  array<string, mixed>  $data
     */
    private function styles(Unit $unit, int $month, int $year, array $data): string
    {
        return view('operasi.laporan.styles')->render()."\n".$this->tables($unit, $month, $year, $data)['styles'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function letterhead(OperasiReport $definition, array $data): string
    {
        return view('operasi.laporan.partials.letterhead', [
            'report' => $data,
            'title' => $definition->title(),
        ])->render();
    }
}
