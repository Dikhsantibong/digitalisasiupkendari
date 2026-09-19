<?php

namespace App\Http\Controllers\Logistik;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Concerns\InteractsWithReportWorkflow;
use App\Http\Controllers\Controller;
use App\Models\LogistikDocumentRecord;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Logistik\LogistikDocumentBuilder;
use App\Services\Operasi\DocumentGridBuilder;
use App\Services\Reports\FragmentGridBuilder;
use App\Services\Reports\OrientationPdfMerger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The editable Laporan Logistik & Gudang: generated from every jadwal & input
 * table, edited as rich text (PDF) or a spreadsheet grid (Excel), saved, and
 * exported to PDF — portrait & landscape sections merged — from exactly that
 * edited content. Mirrors {@see \App\Http\Controllers\Pdm\DocumentController}.
 */
class DocumentController extends Controller
{
    use EmbedsReportLogo;
    use InteractsWithReportWorkflow;

    /**
     * Current report-body template version. Bump when the layout changes so
     * documents saved against an older layout re-render from the new template.
     * v1 = Sampul, Lembar Pengesahan, Daftar Isi, then every jadwal & input
     *      table embedded from its own PDF view.
     */
    private const BODY_VERSION = 2;

    private const FOOTER = 'PT PLN NUSANTARA POWER UP KENDARI - LAPORAN LOGISTIK & GUDANG PEMBANGKIT';

    public function __construct(
        private readonly LogistikDocumentBuilder $builder,
        private readonly FragmentGridBuilder $gridBuilder,
        private readonly DocumentGridBuilder $grids,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();
        $this->authorizeReportView($request, ReportModule::Logistik, PermissionName::LogistikInputView, PermissionName::LogistikLaporanView);

        [$unit, $month, $year] = $this->target($request);
        $data = $this->builder->build($unit, $month, $year);
        $record = $this->currentRecord($unit->id, $month, $year);

        $workflow = $this->reportWorkflows()->present($user, ReportModule::Logistik, $unit, $month, $year);

        return Inertia::render('logistik/laporan/document', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'document_number' => $data['document']['number'],
            'content' => $record?->content_html !== null
                ? $this->withCurrentSignatures($record->content_html, ReportModule::Logistik, $unit, $month, $year)
                : $this->builder->bodyHtml($data),
            'content_styles' => $this->builder->contentStyles($data),
            'letterhead' => $this->builder->letterhead($data),
            'grid' => $record?->content_grid ?? $this->gridBuilder->build($data),
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('logistik.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => $month, 'year' => $year]),
            'can_write' => $user->hasPermissionTo(PermissionName::LogistikInputWrite) && $workflow['editable'],
            'workflow' => $workflow,
        ]);
    }

    public function pdf(Request $request, OrientationPdfMerger $merger): Response
    {
        $this->authorizeReportView($request, ReportModule::Logistik, PermissionName::LogistikLaporanView);

        [$unit, $month, $year] = $this->target($request);
        $data = $this->builder->build($unit, $month, $year);
        $record = $this->currentRecord($unit->id, $month, $year);
        $styles = $this->builder->contentStyles($data);

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            $body = $this->embedAssets($this->builder->letterhead($data).$this->grids->gridToHtml($record->content_grid));
            $pdf = $merger->render($styles, $body, [['show' => '', 'orientation' => 'landscape']], [], self::FOOTER);
        } else {
            $body = $this->embedAssets($record?->content_html !== null
                ? $this->withCurrentSignatures($record->content_html, ReportModule::Logistik, $unit, $month, $year)
                : $this->builder->bodyHtml($data));
            $pdf = $merger->renderSections($styles, $body, 'lg-section', 'lg-landscape', self::FOOTER, unnumberedPages: 1);
        }

        $filename = sprintf('Laporan_Logistik_Gudang_%s_%02d_%d.pdf', str_replace(' ', '_', $unit->name), $month, $year);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::LogistikInputWrite), 403);

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
        $this->ensureReportEditable(ReportModule::Logistik, $unit, $month, $year);
        $data = $this->builder->build($unit, $month, $year);

        $record = $this->recordFor($unit, $month, $year, $user->id);
        $record->document_number = $data['document']['number'];
        $record->format = $validated['format'];
        $record->content_version = self::BODY_VERSION;
        if (($validated['content_html'] ?? null) !== null) {
            $record->content_html = $validated['content_html'];
        }
        if (($validated['content_grid'] ?? null) !== null) {
            $record->content_grid = $validated['content_grid'];
        }
        $record->snapshot = $this->snapshot($data);
        $record->save();

        $this->activityLogger->log(ActivityEvent::Created, "Menyimpan Laporan Logistik & Gudang {$unit->name} periode {$month}/{$year}", $record, unit: $unit->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan Logistik & Gudang disimpan.']);

        return back();
    }

    /**
     * Rebuild the saved document from the latest data & template, discarding
     * any manual edits.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::LogistikInputWrite), 403);

        [$unit, $month, $year] = $this->target($request);
        $this->ensureReportEditable(ReportModule::Logistik, $unit, $month, $year);
        $data = $this->builder->build($unit, $month, $year);

        $record = $this->recordFor($unit, $month, $year, $user->id);
        $record->document_number = $data['document']['number'];
        $record->format = 'html';
        $record->content_version = self::BODY_VERSION;
        $record->content_html = $this->builder->bodyHtml($data);
        $record->content_grid = $this->gridBuilder->build($data);
        $record->snapshot = $this->snapshot($data);
        $record->save();

        $this->activityLogger->log(ActivityEvent::Updated, "Memuat ulang Laporan Logistik & Gudang {$unit->name} periode {$month}/{$year}", $record, unit: $unit->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen dimuat ulang dari data terbaru.']);

        return back();
    }

    /**
     * @return array{0: Unit, 1: int, 2: int}
     */
    private function target(Request $request): array
    {
        $user = $request->user();

        if ($request->filled('unit_id')) {
            $unit = Unit::query()->findOrFail($request->integer('unit_id'));
            abort_unless($user->canAccessUnit($unit), 403);
        } else {
            $unit = Unit::query()->visibleTo($user)->orderBy('name')->first();
            abort_if($unit === null, 403, 'Anda belum ditugaskan pada unit manapun.');
        }

        $now = Carbon::now();

        return [$unit, max(1, min(12, (int) ($request->integer('month') ?: $now->month))), (int) ($request->integer('year') ?: $now->year)];
    }

    private function recordFor(Unit $unit, int $month, int $year, int $userId): LogistikDocumentRecord
    {
        $record = LogistikDocumentRecord::query()->firstOrNew(['unit_id' => $unit->id, 'type' => 'bulanan', 'month' => $month, 'year' => $year]);
        if (! $record->exists) {
            $record->created_by = $userId;
        }

        return $record;
    }

    /**
     * The saved document only when built from the current template version.
     */
    private function currentRecord(int $unitId, int $month, int $year): ?LogistikDocumentRecord
    {
        $record = LogistikDocumentRecord::query()->where('unit_id', $unitId)->where('type', 'bulanan')
            ->where('month', $month)->where('year', $year)->first();

        return $record !== null && $record->content_version === self::BODY_VERSION ? $record : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function snapshot(array $data): array
    {
        return [
            'report' => $data['report'],
            'parts' => array_map(fn (array $part): array => ['key' => $part['key'], 'title' => $part['title'], 'saved' => $part['saved']], $data['parts']),
        ];
    }
}
