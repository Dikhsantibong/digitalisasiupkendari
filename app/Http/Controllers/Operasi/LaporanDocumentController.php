<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\OperasiReportDocument;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Operasi\DocumentGridBuilder;
use App\Services\Operasi\Reports\OperasiReport;
use App\Services\Operasi\Reports\ReportRegistry;
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
    use RendersReportPdf;

    /**
     * Current report-body template version. Bump when the layout changes so
     * documents saved against an older layout re-render from the new template.
     * v2 = full framework (cover, exec summary, daftar isi, istilah) + one
     * section per page; v3 = corporate polish (footer + page numbers, ToC
     * leaders, landscape wide tables).
     */
    private const BODY_VERSION = 3;

    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly DocumentGridBuilder $gridBuilder,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function edit(Request $request, string $report): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        [$definition, $unit, $engine, $month, $year] = $this->resolveTarget($request, $report);

        $data = $definition->build($unit, $month, $year, $engine);
        $documentNumber = $this->documentNumber($unit, $month, $year);
        $record = $this->currentRecord($definition, $unit->id, $engine?->id, $month, $year);

        $grid = $record?->content_grid ?? $this->gridBuilder->forMonthlyReport($data);
        $letterhead = $this->letterhead($definition, $data);

        return Inertia::render('operasi/laporan/document', [
            'report' => ['code' => $definition->code(), 'title' => $definition->title()],
            'filters' => [
                'unit_id' => $unit->id,
                'engine_id' => $engine?->id,
                'month' => $month,
                'year' => $year,
            ],
            'document_number' => $documentNumber,
            'content' => $record?->content_html ?? $this->bodyHtml($definition, $data, $documentNumber),
            'content_styles' => view('operasi.laporan.styles')->render(),
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
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiLaporanView),
        ]);
    }

    public function pdf(Request $request, string $report)
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        [$definition, $unit, $engine, $month, $year] = $this->resolveTarget($request, $report);

        $data = $definition->build($unit, $month, $year, $engine);
        $record = $this->currentRecord($definition, $unit->id, $engine?->id, $month, $year);

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            // Excel mode: the letterhead (logo + kop) is added here — a
            // spreadsheet cannot hold it — above the edited grid body.
            $content = $this->letterhead($definition, $data).$this->gridBuilder->gridToHtml($record->content_grid);
        } else {
            // Text mode: the body already contains the cover + letterhead inline.
            $content = $record?->content_html
                ?? $this->bodyHtml($definition, $data, $this->documentNumber($unit, $month, $year));
        }

        return $this->streamReportPdf(
            $request,
            'operasi.laporan.pdf-shell',
            ['content' => $this->embedAssets($content)],
            "Laporan-{$definition->code()}-{$unit->id}-{$month}-{$year}.pdf",
        );
    }

    public function store(Request $request, string $report): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        [$definition, $unit, $engine, $month, $year] = $this->resolveTarget($request, $report);

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
        $record->content_html = $this->bodyHtml($definition, $data, $this->documentNumber($unit, $month, $year));
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
        if ($definition->requiresEngine()) {
            $engine = Machine::query()->where('unit_id', $unit->id)->findOrFail($request->integer('engine_id'));
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
     * The full report body (cover, executive summary, daftar isi, istilah, and
     * the operasi content sections) — the default text-mode content and PDF.
     *
     * @param  array<string, mixed>  $data
     */
    private function bodyHtml(OperasiReport $definition, array $data, string $documentNumber): string
    {
        return view('operasi.laporan.document-body', [
            'report' => $data,
            'documentNumber' => $documentNumber,
            'reportTitle' => $definition->title(),
        ])->render();
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
