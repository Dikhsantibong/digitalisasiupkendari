<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Concerns\InteractsWithReportWorkflow;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\K3DocumentRecord;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\K3\K3DocumentBuilder;
use App\Services\K3\K3DocumentGridBuilder;
use App\Services\Operasi\DocumentGridBuilder;
use App\Services\Reports\OrientationPdfMerger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The editable K3 monthly-report document. Generated with its figures and ISO
 * numbers filled in, edited as rich text (PDF) or a spreadsheet grid (Excel),
 * saved, and exported to PDF from exactly that edited content. Mirrors
 * {@see \App\Http\Controllers\Har\DocumentController}.
 */
class DocumentController extends Controller
{
    use EmbedsReportLogo;
    use InteractsWithReportWorkflow;
    use RendersReportPdf;

    /**
     * Current report-body template version. Bump when the layout changes so
     * documents saved against an older layout re-render from the new template.
     * v3 = full framework (cover, exec summary, daftar isi, istilah) + one
     * section per page; v4 = corporate polish (footer + page numbers, ToC leaders);
     * v5 = redesigned corporate cover with dual logos (PLN NP + MKP) and title Laporan K3 Lingkungan Pembangkit;
     * v6 = restructured to match official standard (Kop 3 kolom, Daftar Isi I-VII, Lembar Pengesahan, Resume Statistik, 31 bab + red lines);
     * v7 = multi-orientation merging via PDFium, kop on every page with PLN and K3 logos, individual pagination per point;
     * v8 = integrated Jadwal Pekerjaan Rutin K3L & Lingkungan 11 KIT table in Landscape mode;
     * v9 = integrated Logbook Pemantauan Pemanfaatan Air Limbah in Landscape mode;
     * v10 = one `.k3-section` per Daftar Isi point (formulir portrait, other tables
     *       landscape), red line when a point has no data, Daftar Isi page numbers
     *       filled at export, cover unnumbered;
     * v11 = input-backed points reuse the K3 input export tables (K3InputTables),
     *       more points filled (patrol check matrix, program kerja, hydrant recap, kecelakaan);
     * v12 = tables mirror the input/jadwal pages (defaults included) and the formulir
     *       points are filled from the jadwal rows that record that work;
     * v13 = always render full table layout for Daftar Monitoring Sertifikasi Peralatan (poin 19)
     *       with standard equipment baseline and K3 logo on Kop header.
     */
    private const BODY_VERSION = 14;

    private const FOOTER = 'PT PLN NUSANTARA POWER UP KENDARI - LAPORAN K3 LINGKUNGAN PEMBANGKIT';

    public function __construct(
        private readonly K3DocumentBuilder $builder,
        private readonly K3DocumentGridBuilder $gridBuilder,
        private readonly DocumentGridBuilder $grids,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();
        $this->authorizeReportView($request, ReportModule::K3, PermissionName::K3InputView, PermissionName::K3LaporanView);

        $unit = $this->resolveUnit($request);
        $now = now();
        [$month, $year] = [
            (int) ($request->integer('month') ?: $now->month),
            (int) ($request->integer('year') ?: $now->year),
        ];

        $data = $this->builder->build($unit, $month, $year);
        $record = $this->currentRecord($unit->id, $month, $year);

        $workflow = $this->reportWorkflows()->present($user, ReportModule::K3, $unit, $month, $year);

        return Inertia::render('k3/laporan/document', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'document_number' => $data['document']['number'],
            'content' => $record?->content_html !== null
                ? $this->withCurrentSignatures($record->content_html, ReportModule::K3, $unit, $month, $year)
                : $this->builder->bodyHtml($data),
            'content_styles' => $this->builder->contentStyles(),
            'letterhead' => $this->builder->letterhead($data),
            'grid' => $record?->content_grid ?? $this->gridBuilder->build($data),
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('k3.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => $month, 'year' => $year]),
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite) && $workflow['editable'],
            'workflow' => $workflow,
        ]);
    }

    public function pdf(Request $request, OrientationPdfMerger $merger): Response
    {
        $user = $request->user();
        $this->authorizeReportView($request, ReportModule::K3, PermissionName::K3LaporanView);

        $unit = $this->resolveUnit($request);
        $now = now();
        [$month, $year] = [
            (int) ($request->integer('month') ?: $now->month),
            (int) ($request->integer('year') ?: $now->year),
        ];

        $data = $this->builder->build($unit, $month, $year);
        $record = $this->currentRecord($unit->id, $month, $year);
        $styles = $this->builder->contentStyles();

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            $body = $this->embedAssets($this->builder->letterhead($data).$this->grids->gridToHtml($record->content_grid));
            $pdf = $merger->render($styles, $body, [['show' => '', 'orientation' => 'landscape']], [], self::FOOTER);
        } else {
            $body = $this->embedAssets($record?->content_html !== null
                ? $this->withCurrentSignatures($record->content_html, ReportModule::K3, $unit, $month, $year)
                : $this->builder->bodyHtml($data));
            $pdf = $merger->renderSections($styles, $body, 'k3-section', 'k3-landscape', self::FOOTER, unnumberedPages: 1);
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="Laporan-K3-'.$unit->id.'-'.$month.'-'.$year.'.pdf"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

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
        $this->ensureReportEditable(ReportModule::K3, $unit, $month, $year);
        $data = $this->builder->build($unit, $month, $year);

        $record = K3DocumentRecord::query()->firstOrNew([
            'unit_id' => $unit->id, 'type' => 'bulanan', 'month' => $month, 'year' => $year,
        ]);
        if (! $record->exists) {
            $record->created_by = $user->id;
        }
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
            "Menyimpan Laporan K3 {$unit->name} periode {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan K3 disimpan.']);

        return back();
    }

    /**
     * Rebuild the saved document from the latest data & template, discarding any
     * manual edits — so an improved report layout reaches a document that was
     * already saved with the old layout.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $unit = $this->resolveUnit($request);
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];
        $this->ensureReportEditable(ReportModule::K3, $unit, $month, $year);

        $data = $this->builder->build($unit, $month, $year);

        $record = K3DocumentRecord::query()->firstOrNew([
            'unit_id' => $unit->id, 'type' => 'bulanan', 'month' => $month, 'year' => $year,
        ]);
        if (! $record->exists) {
            $record->created_by = $user->id;
        }
        $record->document_number = $data['document']['number'];
        $record->format = 'html';
        $record->content_version = self::BODY_VERSION;
        $record->content_html = $this->builder->bodyHtml($data);
        $record->content_grid = $this->gridBuilder->build($data);
        $record->snapshot = $data['report'];
        $record->save();

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Memuat ulang Laporan K3 {$unit->name} periode {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen dimuat ulang dari data terbaru.']);

        return back();
    }

    private function existingRecord(int $unitId, int $month, int $year): ?K3DocumentRecord
    {
        return K3DocumentRecord::query()
            ->where('unit_id', $unitId)->where('type', 'bulanan')
            ->where('month', $month)->where('year', $year)
            ->first();
    }

    /**
     * The saved document only when built from the current template version; a
     * stale one is ignored so the fresh template renders instead.
     */
    private function currentRecord(int $unitId, int $month, int $year): ?K3DocumentRecord
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
