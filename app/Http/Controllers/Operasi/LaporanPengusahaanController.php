<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Controller;
use App\Models\OperasiReportDocument;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Operasi\DocumentGridBuilder;
use App\Services\Operasi\OperasiPengusahaanDocument;
use App\Services\Reports\OrientationPdfMerger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Laporan Pengusahaan Pembangkit (Operasi) — Akses 2: the document built from
 * every Pengusahaan Operasi input (see {@see OperasiPengusahaanDocument}),
 * viewed by TL / Staf Operasi & Manager UL (operasi.pengusahaan.view), edited
 * and saved by TL / Staf (operasi.pengusahaan.write). Saved as an
 * operasi_report_documents row with report_code "pengusahaan".
 */
class LaporanPengusahaanController extends Controller
{
    use EmbedsReportLogo;

    public const REPORT_CODE = 'pengusahaan';

    private const BODY_VERSION = 1;

    private const FOOTER = 'PT PLN NUSANTARA POWER UP KENDARI - LAPORAN PENGUSAHAAN PEMBANGKIT (OPERASI)';

    public function __construct(
        private readonly OperasiPengusahaanDocument $document,
        private readonly DocumentGridBuilder $grids,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiPengusahaanView), 403);

        [$unit, $month, $year] = $this->target($request);
        $data = $this->document->build($unit, $month, $year);
        $record = $this->currentRecord($unit->id, $month, $year);

        return Inertia::render('operasi/laporan/pengusahaan', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'document_number' => $data['document']['number'],
            'content' => $record?->content_html ?? $this->document->bodyHtml($data),
            'content_styles' => $this->document->styles(),
            'letterhead' => $this->document->letterhead($data),
            'grid' => $record?->content_grid ?? $this->document->grid($data),
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('operasi.laporan.pengusahaan.pdf', ['unit_id' => $unit->id, 'month' => $month, 'year' => $year]),
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiPengusahaanWrite),
        ]);
    }

    public function pdf(Request $request, OrientationPdfMerger $merger): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiPengusahaanView), 403);

        [$unit, $month, $year] = $this->target($request);
        $data = $this->document->build($unit, $month, $year);
        $record = $this->currentRecord($unit->id, $month, $year);
        $styles = $this->document->styles();

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            $body = $this->embedAssets($this->document->letterhead($data).$this->grids->gridToHtml($record->content_grid));
            $pdf = $merger->render($styles, $body, [['show' => '', 'orientation' => 'landscape']], [], self::FOOTER);
        } else {
            $body = $this->embedAssets($record?->content_html ?? $this->document->bodyHtml($data));
            // Portrait cover, pengesahan & narrow tables; landscape wide tables.
            $segments = array_values(array_filter([
                ['show' => 'seg-cover-info', 'orientation' => 'portrait'],
                ['show' => 'seg-tables-wide', 'orientation' => 'landscape'],
            ], fn (array $segment): bool => str_contains($body, $segment['show'])));
            $pdf = $merger->render($styles, $body, $segments, array_column($segments, 'show'), self::FOOTER);
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="Laporan-Pengusahaan-Operasi-'.$unit->id.'-'.$month.'-'.$year.'.pdf"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiPengusahaanWrite), 403);

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
        [$month, $year] = [(int) $validated['month'], (int) $validated['year']];

        $data = $this->document->build($unit, $month, $year);
        $record = $this->recordFor($unit->id, $month, $year, $user->id);
        $record->document_number = $data['document']['number'];
        $record->format = $validated['format'];
        $record->content_version = self::BODY_VERSION;
        if (($validated['content_html'] ?? null) !== null) {
            $record->content_html = $validated['content_html'];
        }
        if (($validated['content_grid'] ?? null) !== null) {
            $record->content_grid = $validated['content_grid'];
        }
        $record->snapshot = $data['report'];
        $record->save();

        $this->activityLogger->log(ActivityEvent::Created, "Menyimpan Laporan Pengusahaan Operasi {$unit->name} periode {$month}/{$year}", $record, unit: $unit->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan Pengusahaan Operasi berhasil disimpan.']);

        return back();
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiPengusahaanWrite), 403);

        [$unit, $month, $year] = $this->target($request);
        $data = $this->document->build($unit, $month, $year);

        $record = $this->recordFor($unit->id, $month, $year, $user->id);
        $record->document_number = $data['document']['number'];
        $record->format = 'html';
        $record->content_version = self::BODY_VERSION;
        $record->content_html = $this->document->bodyHtml($data);
        $record->content_grid = $this->document->grid($data);
        $record->snapshot = $data['report'];
        $record->save();

        $this->activityLogger->log(ActivityEvent::Updated, "Memuat ulang Laporan Pengusahaan Operasi {$unit->name} periode {$month}/{$year}", $record, unit: $unit->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen Laporan Pengusahaan Operasi dimuat ulang dari data terbaru.']);

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
            $unit = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->first();
            abort_if($unit === null, 403, 'Anda belum ditugaskan pada unit manapun.');
        }

        $now = now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);
        abort_unless($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100, 404);

        return [$unit, $month, $year];
    }

    private function recordFor(int $unitId, int $month, int $year, int $userId): OperasiReportDocument
    {
        $record = OperasiReportDocument::query()->firstOrNew([
            'unit_id' => $unitId, 'report_code' => self::REPORT_CODE, 'engine_id' => null, 'month' => $month, 'year' => $year,
        ]);
        if (! $record->exists) {
            $record->created_by = $userId;
        }

        return $record;
    }

    private function currentRecord(int $unitId, int $month, int $year): ?OperasiReportDocument
    {
        $record = OperasiReportDocument::query()
            ->where('unit_id', $unitId)->where('report_code', self::REPORT_CODE)->whereNull('engine_id')
            ->where('month', $month)->where('year', $year)
            ->first();

        return $record !== null && (int) $record->content_version === self::BODY_VERSION ? $record : null;
    }
}
