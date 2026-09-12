<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3DocumentRecord;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\K3\K3DocumentBuilder;
use App\Services\K3\K3DocumentGridBuilder;
use App\Services\Operasi\DocumentGridBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The editable K3 monthly-report document. Generated with its figures and ISO
 * numbers filled in, edited as rich text (PDF) or a spreadsheet grid (Excel),
 * saved, and exported to PDF from exactly that edited content. Mirrors
 * {@see \App\Http\Controllers\Har\DocumentController}.
 */
class DocumentController extends Controller
{
    public function __construct(
        private readonly K3DocumentBuilder $builder,
        private readonly K3DocumentGridBuilder $gridBuilder,
        private readonly DocumentGridBuilder $grids,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function edit(Request $request): Response
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
        $record = $this->existingRecord($unit->id, $month, $year);

        return Inertia::render('k3/laporan/document', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'document_number' => $data['document']['number'],
            'content' => $record?->content_html ?? $this->builder->bodyHtml($data),
            'content_styles' => $this->builder->contentStyles(),
            'letterhead' => $this->builder->letterhead($data),
            'grid' => $record?->content_grid ?? $this->gridBuilder->build($data),
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('k3.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => $month, 'year' => $year]),
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
        ]);
    }

    public function pdf(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3LaporanView), 403);

        $unit = $this->resolveUnit($request);
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];

        $data = $this->builder->build($unit, $month, $year);
        $record = $this->existingRecord($unit->id, $month, $year);

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            $content = $this->builder->letterhead($data).$this->grids->gridToHtml($record->content_grid);
        } else {
            $content = $record?->content_html ?? $this->builder->bodyHtml($data);
        }

        $pdf = Pdf::loadView('k3.laporan.pdf-shell', ['content' => $this->embedAssets($content)])->setPaper('a4');
        $filename = "Laporan-K3-{$unit->id}-{$month}-{$year}.pdf";

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
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
            'unit_id' => $unit->id, 'type' => 'bulanan', 'month' => $month, 'year' => $year,
        ]);
        if (! $record->exists) {
            $record->created_by = $user->id;
        }
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
            "Menyimpan Laporan K3 {$unit->name} periode {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan K3 disimpan.']);

        return back();
    }

    private function existingRecord(int $unitId, int $month, int $year): ?K3DocumentRecord
    {
        return K3DocumentRecord::query()
            ->where('unit_id', $unitId)->where('type', 'bulanan')
            ->where('month', $month)->where('year', $year)
            ->first();
    }

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
