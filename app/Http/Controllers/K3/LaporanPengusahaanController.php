<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
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

class LaporanPengusahaanController extends Controller
{
    use EmbedsReportLogo;

    private const BODY_VERSION = 1;

    private const FOOTER = 'PT PLN NUSANTARA POWER UP KENDARI - LAPORAN KINERJA K3 & KAM';

    public function __construct(
        private readonly K3DocumentBuilder $builder,
        private readonly K3DocumentGridBuilder $gridBuilder,
        private readonly DocumentGridBuilder $grids,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3LaporanView), 403);

        $unit = $this->resolveUnit($request);
        $now = now();
        [$month, $year] = [
            (int) ($request->integer('month') ?: $now->month),
            (int) ($request->integer('year') ?: $now->year),
        ];

        $data = $this->builder->build($unit, $month, $year);
        $record = $this->currentRecord($unit->id, $month, $year);

        return Inertia::render('k3/laporan/pengusahaan', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'document_number' => $data['document']['number'] ?? 'FMKD-314-10.3.3',
            'content' => $record?->content_html ?? $this->builder->pengusahaanBodyHtml($data),
            'content_styles' => $this->builder->pengusahaanStyles(),
            'letterhead' => $this->builder->letterhead($data),
            'grid' => $record?->content_grid ?? $this->gridBuilder->build($data),
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('k3.laporan.pengusahaan.pdf', [
                'unit_id' => $unit->id, 'month' => $month, 'year' => $year,
            ]),
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
        ]);
    }

    public function pdf(Request $request, OrientationPdfMerger $merger): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3LaporanView), 403);

        $unit = $this->resolveUnit($request);
        $now = now();
        [$month, $year] = [
            (int) ($request->integer('month') ?: $now->month),
            (int) ($request->integer('year') ?: $now->year),
        ];

        $data = $this->builder->build($unit, $month, $year);
        $record = $this->currentRecord($unit->id, $month, $year);
        $styles = $this->builder->pengusahaanStyles();

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            $body = $this->embedAssets($this->builder->letterhead($data).$this->grids->gridToHtml($record->content_grid));
            $pdf = $merger->render($styles, $body, [['show' => '', 'orientation' => 'landscape']], [], self::FOOTER);
        } else {
            $body = $this->embedAssets($record?->content_html ?? $this->builder->pengusahaanBodyHtml($data));
            $pdf = $merger->render($styles, $body, [
                ['show' => 'seg-cover-info', 'orientation' => 'portrait'],
                ['show' => 'seg-tables-wide', 'orientation' => 'landscape'],
                ['show' => 'seg-attachments', 'orientation' => 'portrait'],
            ], ['seg-cover-info', 'seg-tables-wide', 'seg-attachments'], self::FOOTER);
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="Laporan-Pengusahaan-K3-'.$unit->id.'-'.$month.'-'.$year.'.pdf"',
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

        $data = $this->builder->build($unit, $month, $year);

        $record = K3DocumentRecord::query()->firstOrNew([
            'unit_id' => $unit->id, 'type' => 'pengusahaan', 'month' => $month, 'year' => $year,
        ]);
        if (! $record->exists) {
            $record->created_by = $user->id;
        }
        $record->document_number = $data['document']['number'] ?? 'FMKD-314-10.3.3';
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
            "Menyimpan Laporan Pengusahaan K3 {$unit->name} periode {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan Pengusahaan K3 berhasil disimpan.']);

        return back();
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $unit = $this->resolveUnit($request);
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];

        $data = $this->builder->build($unit, $month, $year);

        $record = K3DocumentRecord::query()->firstOrNew([
            'unit_id' => $unit->id, 'type' => 'pengusahaan', 'month' => $month, 'year' => $year,
        ]);
        if (! $record->exists) {
            $record->created_by = $user->id;
        }
        $record->document_number = $data['document']['number'] ?? 'FMKD-314-10.3.3';
        $record->format = 'html';
        $record->content_version = self::BODY_VERSION;
        $record->content_html = $this->builder->pengusahaanBodyHtml($data);
        $record->content_grid = $this->gridBuilder->build($data);
        $record->snapshot = $data['report'];
        $record->save();

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Memuat ulang Laporan Pengusahaan K3 {$unit->name} periode {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen Laporan Pengusahaan K3 dimuat ulang dari data terbaru.']);

        return back();
    }

    private function existingRecord(int $unitId, int $month, int $year): ?K3DocumentRecord
    {
        return K3DocumentRecord::query()
            ->where('unit_id', $unitId)->where('type', 'pengusahaan')
            ->where('month', $month)->where('year', $year)
            ->first();
    }

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
