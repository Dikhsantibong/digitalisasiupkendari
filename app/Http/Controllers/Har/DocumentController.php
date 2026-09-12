<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HarDocumentRecord;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Har\HarDocumentBuilder;
use App\Services\Har\HarDocumentGridBuilder;
use App\Services\Operasi\DocumentGridBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function __construct(
        private readonly HarDocumentBuilder $builder,
        private readonly HarDocumentGridBuilder $gridBuilder,
        private readonly DocumentGridBuilder $grids,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarLaporanView), 403);

        $unit = $this->resolveUnit($request);
        $now = now();
        [$month, $year] = [
            (int) ($request->integer('month') ?: $now->month),
            (int) ($request->integer('year') ?: $now->year),
        ];

        $data = $this->builder->build($unit, $month, $year);
        $record = $this->existingRecord($unit->id, $month, $year);

        return Inertia::render('har/laporan/document', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'document_number' => $data['document']['number'],
            'content' => $record?->content_html ?? $this->builder->bodyHtml($data),
            'content_styles' => $this->builder->contentStyles(),
            'letterhead' => $this->builder->letterhead($data),
            'grid' => $record?->content_grid ?? $this->gridBuilder->build($data),
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('har.laporan.document.pdf', [
                'unit_id' => $unit->id, 'month' => $month, 'year' => $year,
            ]),
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function pdf(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarLaporanView), 403);

        $unit = $this->resolveUnit($request);
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];

        $data = $this->builder->build($unit, $month, $year);
        $record = $this->existingRecord($unit->id, $month, $year);

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            // Excel mode: the letterhead (logo + kop) is added here — a
            // spreadsheet cannot hold it — above the edited grid body.
            $content = $this->builder->letterhead($data).$this->grids->gridToHtml($record->content_grid);
        } else {
            // Text mode: the body already contains the letterhead inline.
            $content = $record?->content_html ?? $this->builder->bodyHtml($data);
        }

        $pdf = Pdf::loadView('har.laporan.pdf-shell', [
            'content' => $this->embedAssets($content),
        ])->setPaper('a4');
        $filename = "Laporan-HAR-{$unit->id}-{$month}-{$year}.pdf";

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
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

    private function existingRecord(int $unitId, int $month, int $year): ?HarDocumentRecord
    {
        return HarDocumentRecord::query()
            ->where('unit_id', $unitId)->where('type', 'bulanan')
            ->where('month', $month)->where('year', $year)
            ->first();
    }

    /**
     * Inline the letterhead logo as a data URI so dompdf renders it without any
     * remote-file access. The stored/edited HTML keeps the small public URL,
     * which the browser editor loads directly.
     */
    private function embedAssets(string $html): string
    {
        $path = public_path('logo/sidebar-logo.png');

        if (! is_file($path)) {
            return $html;
        }

        $dataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));

        return str_replace('/logo/sidebar-logo.png', $dataUri, $html);
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
