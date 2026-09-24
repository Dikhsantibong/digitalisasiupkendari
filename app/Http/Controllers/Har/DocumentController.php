<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Concerns\InteractsWithReportWorkflow;
use App\Http\Controllers\Controller;
use App\Models\HarDocumentRecord;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Har\HarDocumentBuilder;
use App\Services\Har\HarDocumentGridBuilder;
use App\Services\Operasi\DocumentGridBuilder;
use App\Services\Reports\OrientationPdfMerger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The editable HAR monthly-report document. The report is generated with its
 * figures and ISO numbers filled in, opened in a Word-like editor (or an
 * Excel-like grid) for the user to fine-tune, saved as editable HTML/grid, and
 * exported to PDF (dompdf) from exactly that edited content.
 */
class DocumentController extends Controller
{
    use EmbedsReportLogo;
    use InteractsWithReportWorkflow;

    /**
     * The current report-body template version. Bump this whenever the report
     * layout changes so documents saved against an older layout are treated as
     * stale and re-rendered from the current template automatically.
     * v2 = 14-section layout + cover; v3 = each section on its own page;
     * v4 = corporate polish (footer + page numbers, ToC leaders, full WO tables
     * in landscape); v5 = PLN NP corporate kop; v6 = Service Request Summary added;
     * v7 = Maintenance Summary added; v8 = Rekapitulasi WO Task added;
     * v9 = WO Preventive Maintenance ISO FMKD-314-10.3.3-A12 added;
     * v10 = WO Predictive Maintenance ISO FMKD-314-10.3.3-A13 added;
     * v11 = WO Corrective Maintenance ISO FMKD-314-10.3.3-A14 added;
     * v12 = Removed hardcoded fallback dummy data across all report sections and seeded authentic maintenance data;
     * v13 = Converted Service Request Map and Summary charts to base64 SVG images for Dompdf & TinyMCE compatibility;
     * v14 = Redesigned corporate cover with dual logos (PLN NP + MKP) and title Laporan Pemeliharaan Pembangkit;
     * v17 = Added Resume Statistik Pemeliharaan Pembangkit page after Lembar Pengesahan with Project Leader & Koordinator Pemeliharaan signatories;
     * v18 = Restructured into separate Laporan Pemeliharaan (with complete schedules) and restored clean corporate polygon cover;
     * v19 = Lembar Pengesahan & tanda tangan laporan from the report workflow (ReportWorkflowService);
     * v20 = portrait/landscape sections merged (OrientationPdfMerger) + embedded jadwal lembar,
     * formulir (Daily Meeting, Logbook Mutasi, LH-05) & input tables (Rekap/Abnormal Gangguan,
     * Patrol Check, 5S5R), Daftar Isi page numbers.
     */
    private const BODY_VERSION = 20;

    private const FOOTER = 'PT PLN NUSANTARA POWER UP KENDARI - LAPORAN PEMELIHARAAN PEMBANGKIT';

    public function __construct(
        private readonly HarDocumentBuilder $builder,
        private readonly HarDocumentGridBuilder $gridBuilder,
        private readonly DocumentGridBuilder $grids,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeReportView($request, ReportModule::Har, PermissionName::HarLaporanView);

        $unit = $this->resolveUnit($request);
        $now = now();
        [$month, $year] = [
            (int) ($request->integer('month') ?: $now->month),
            (int) ($request->integer('year') ?: $now->year),
        ];

        $data = $this->builder->build($unit, $month, $year);
        $record = $this->currentRecord($unit->id, $month, $year);

        $workflow = $this->reportWorkflows()->present($user, ReportModule::Har, $unit, $month, $year);

        return Inertia::render('har/laporan/document', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'document_number' => $data['document']['number'],
            'content' => $record?->content_html !== null
                ? $this->withCurrentSignatures($record->content_html, ReportModule::Har, $unit, $month, $year)
                : $this->builder->bodyHtml($data),
            'content_styles' => $this->builder->contentStyles($data),
            'letterhead' => $this->builder->letterhead($data),
            'grid' => $record?->content_grid ?? $this->gridBuilder->build($data),
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('har.laporan.document.pdf', [
                'unit_id' => $unit->id, 'month' => $month, 'year' => $year,
            ]),
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite) && $workflow['editable'],
            'workflow' => $workflow,
        ]);
    }

    public function pdf(Request $request, OrientationPdfMerger $merger): HttpResponse
    {
        $user = $request->user();
        $this->authorizeReportView($request, ReportModule::Har, PermissionName::HarLaporanView);

        $unit = $this->resolveUnit($request);
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];

        $data = $this->builder->build($unit, $month, $year);
        $record = $this->currentRecord($unit->id, $month, $year);

        $styles = $this->builder->contentStyles($data);

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            // Excel mode: the letterhead (logo + kop) is added here — a
            // spreadsheet cannot hold it — above the edited grid body.
            $body = $this->embedAssets($this->builder->letterhead($data).$this->grids->gridToHtml($record->content_grid));
            $pdf = $merger->render($styles, $body, [['show' => '', 'orientation' => 'landscape']], [], self::FOOTER);
        } else {
            // Text mode: the body already contains the letterhead inline; each
            // `.har-section` prints on its own orientation (jadwal & input
            // tables landscape) and the parts are merged into one PDF.
            $body = $this->embedAssets($record?->content_html !== null
                ? $this->withCurrentSignatures($record->content_html, ReportModule::Har, $unit, $month, $year)
                : $this->builder->bodyHtml($data));
            $pdf = $merger->renderSections($styles, $body, 'har-section', 'har-landscape', self::FOOTER, unnumberedPages: 1);
        }

        $filename = "Laporan-HAR-{$unit->id}-{$month}-{$year}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'format' => ['required', Rule::in(['html', 'grid'])],
            'content_html' => ['required_if:format,html', 'nullable', 'string'],
            'content_grid' => ['required_if:format,grid', 'nullable', 'array'],
        ]);

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $this->ensureReportEditable(ReportModule::Har, $unit, $month, $year);

        $data = $this->builder->build($unit, $month, $year);
        $period = ReportPeriod::query()
            ->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();

        $record = HarDocumentRecord::query()->firstOrNew([
            'unit_id' => $unit->id, 'type' => 'bulanan', 'month' => $month, 'year' => $year,
        ]);
        if (! $record->exists) {
            $record->created_by = $user->id;
        }
        $record->report_period_id = $period?->id;
        $record->document_number = $data['document']['number'];
        $record->format = $validated['format'];
        $record->content_version = self::BODY_VERSION;
        if (array_key_exists('content_html', $validated) && $validated['content_html'] !== null) {
            $record->content_html = $validated['content_html'];
        }
        if (array_key_exists('content_grid', $validated) && $validated['content_grid'] !== null) {
            $record->content_grid = $validated['content_grid'];
        }
        $record->snapshot = $data['report'];
        $record->save();

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menyimpan Laporan HAR {$unit->name} periode {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan HAR disimpan.']);

        return back();
    }

    /**
     * Rebuild the saved document from the latest data & template, discarding any
     * manual edits. Lets an improved report layout be pulled into a document that
     * was already saved with the old layout.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unit = $this->resolveUnit($request);
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];
        $this->ensureReportEditable(ReportModule::Har, $unit, $month, $year);

        $data = $this->builder->build($unit, $month, $year);
        $period = ReportPeriod::query()
            ->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();

        $record = HarDocumentRecord::query()->firstOrNew([
            'unit_id' => $unit->id, 'type' => 'bulanan', 'month' => $month, 'year' => $year,
        ]);
        if (! $record->exists) {
            $record->created_by = $user->id;
        }
        $record->report_period_id = $period?->id;
        $record->document_number = $data['document']['number'];
        $record->format = 'html';
        $record->content_version = self::BODY_VERSION;
        $record->content_html = $this->builder->bodyHtml($data);
        $record->content_grid = $this->gridBuilder->build($data);
        $record->snapshot = $data['report'];
        $record->save();

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Memuat ulang Laporan HAR {$unit->name} periode {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen dimuat ulang dari data terbaru.']);

        return back();
    }

    private function existingRecord(int $unitId, int $month, int $year): ?HarDocumentRecord
    {
        return HarDocumentRecord::query()
            ->where('unit_id', $unitId)->where('type', 'bulanan')
            ->where('month', $month)->where('year', $year)
            ->first();
    }

    /**
     * The saved document only when it was built from the current template
     * version; a stale one is ignored so the fresh template renders instead.
     */
    private function currentRecord(int $unitId, int $month, int $year): ?HarDocumentRecord
    {
        $record = $this->existingRecord($unitId, $month, $year);

        return $record !== null && (int) $record->content_version === self::BODY_VERSION ? $record : null;
    }

    private function resolveUnit(Request $request): Unit
    {
        $user = $request->user();

        if ($request->filled('unit_id')) {
            $unit = Unit::query()->findOrFail($request->integer('unit_id'));
            abort_unless($user->canAccessUnit($unit), 403);

            return $unit;
        }

        $unit = Unit::query()->visibleTo($user)->orderBy('name')->first();
        abort_if($unit === null, 403, 'Anda belum ditugaskan pada unit manapun.');

        return $unit;
    }
}
