<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\ScheduleGroupType;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\OperatorAbsensiDocument;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Operator\AbsensiDocumentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The editable Operator attendance/schedule report document — same feature as
 * the logsheet report but per unit + month + employee group. Read-only for
 * viewers; only OperatorAbsensiWrite may save.
 */
class AbsensiReportController extends Controller
{
    use EmbedsReportLogo;
    use RendersReportPdf;

    /** Report-body template version; bump to auto-refresh documents saved on an older layout. */
    private const BODY_VERSION = 1;

    public function __construct(
        private readonly AbsensiDocumentBuilder $builder,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorAbsensiView), 403);

        [$unit, $groupType, $year, $month] = $this->resolveTarget($request);

        $data = $this->builder->build($unit, $month, $year, $groupType);
        $record = $this->currentRecord($unit->id, $year, $month, $groupType);
        $grid = $record?->content_grid ?? $this->builder->grid($data);

        return Inertia::render('operator/laporan/absensi-document', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'group_type' => $groupType->value],
            'document_number' => $data['document_number'],
            'content' => $record?->content_html ?? $this->builder->bodyHtml($data),
            'content_styles' => $this->builder->contentStyles(),
            'letterhead' => $this->builder->letterhead($data),
            'grid' => $grid,
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('operator.laporan.absensi.pdf', [
                'unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'group_type' => $groupType->value,
            ]),
            'can_write' => $user->hasPermissionTo(PermissionName::OperatorAbsensiWrite),
        ]);
    }

    public function pdf(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorAbsensiView), 403);

        [$unit, $groupType, $year, $month] = $this->resolveTarget($request);

        $data = $this->builder->build($unit, $month, $year, $groupType);
        $record = $this->currentRecord($unit->id, $year, $month, $groupType);

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            $content = $this->builder->letterhead($data).$this->builder->gridToHtml($record->content_grid);
        } else {
            $content = $record?->content_html ?? $this->builder->bodyHtml($data);
        }

        return $this->streamReportPdf(
            $request,
            'operator.laporan.pdf-shell',
            ['content' => $this->embedAssets($content)],
            "Absensi-{$unit->id}-{$year}-{$month}-{$groupType->value}.pdf",
        );
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->persist($request, false);
    }

    /** Rebuild from the latest data & template, discarding manual edits. */
    public function regenerate(Request $request): RedirectResponse
    {
        return $this->persist($request, true);
    }

    private function persist(Request $request, bool $regenerate): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorAbsensiWrite), 403);

        [$unit, $groupType, $year, $month] = $this->resolveTarget($request);
        $data = $this->builder->build($unit, $month, $year, $groupType);

        $validated = $regenerate ? ['format' => 'html'] : $request->validate([
            'format' => ['required', Rule::in(['html', 'grid'])],
            'content_html' => ['required_if:format,html', 'nullable', 'string'],
            'content_grid' => ['required_if:format,grid', 'nullable', 'array'],
        ]);

        $record = OperatorAbsensiDocument::query()->firstOrNew([
            'unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'group_type' => $groupType->value,
        ]);
        if (! $record->exists) {
            $record->created_by = $user->id;
        }
        $record->document_number = $data['document_number'];
        $record->content_version = self::BODY_VERSION;
        $record->format = $validated['format'];

        if ($regenerate) {
            $record->content_html = $this->builder->bodyHtml($data);
            $record->content_grid = $this->builder->grid($data);
        } else {
            if (($validated['content_html'] ?? null) !== null) {
                $record->content_html = $validated['content_html'];
            }
            if (($validated['content_grid'] ?? null) !== null) {
                $record->content_grid = $validated['content_grid'];
            }
        }
        $record->snapshot = $data;
        $record->save();

        $this->activityLogger->log(
            ActivityEvent::Updated,
            ($regenerate ? 'Memuat ulang' : 'Menyimpan')." Dokumen Absensi {$groupType->label()} {$unit->name} {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $regenerate ? 'Dokumen dimuat ulang dari data terbaru.' : 'Dokumen absensi disimpan.']);

        return back();
    }

    /**
     * @return array{0: Unit, 1: ScheduleGroupType, 2: int, 3: int}
     */
    private function resolveTarget(Request $request): array
    {
        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($request->user()->canAccessUnit($unit), 403);

        $request->validate([
            'unit_id' => ['required', 'integer'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'group_type' => ['required', Rule::in(array_column(ScheduleGroupType::cases(), 'value'))],
        ]);

        return [
            $unit,
            ScheduleGroupType::from($request->string('group_type')->value()),
            (int) $request->integer('year'),
            (int) $request->integer('month'),
        ];
    }

    private function currentRecord(int $unitId, int $year, int $month, ScheduleGroupType $groupType): ?OperatorAbsensiDocument
    {
        $record = OperatorAbsensiDocument::query()
            ->where('unit_id', $unitId)->where('year', $year)
            ->where('month', $month)->where('group_type', $groupType->value)->first();

        return $record !== null && (int) $record->content_version === self::BODY_VERSION ? $record : null;
    }
}
