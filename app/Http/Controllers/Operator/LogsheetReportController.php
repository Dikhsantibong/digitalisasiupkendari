<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\ScheduleGroupType;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\OperatorLogsheetDocument;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Operator\LogsheetDocumentBuilder;
use App\Services\Reports\OrientationPdfMerger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Menu Laporan (modul OPERATOR): the editable logsheet report document — same
 * feature as the other modules' reports. The daily logsheet is generated into a
 * document, opened in the shared editor (Word-like text / Excel-like grid),
 * saved, and exported to PDF from exactly that content. One document per machine
 * per day. Read-only for viewers; only OperatorLogsheetWrite may save.
 */
class LogsheetReportController extends Controller
{
    use EmbedsReportLogo;

    /** Report-body template version; bump to auto-refresh documents saved on an older layout. */
    private const BODY_VERSION = 1;

    private const FOOTER = 'Dicetak oleh Sistem Manajemen Terintegrasi Pembangkitan - PT PLN Nusantara Power UP Kendari';

    public function __construct(
        private readonly LogsheetDocumentBuilder $builder,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $engine = $machines->firstWhere('id', (int) $request->integer('engine_id')) ?? $machines->first();
        $now = Carbon::now();

        return Inertia::render('operator/laporan/index', [
            'filters' => [
                'unit_id' => $unit->id,
                'engine_id' => $engine?->id,
                'log_date' => ($request->date('log_date') ?? $now)->toDateString(),
                'month' => (int) ($request->integer('month') ?: $now->month),
                'year' => (int) ($request->integer('year') ?: $now->year),
                'group_type' => ScheduleGroupType::tryFrom((string) $request->query('group_type'))?->value ?? ScheduleGroupType::Shift->value,
            ],
            'options' => [
                'units' => $units->all(),
                'machines' => $machines->all(),
                'years' => range($now->year - 1, $now->year + 1),
                'group_types' => array_map(
                    fn (ScheduleGroupType $t): array => ['value' => $t->value, 'label' => $t->label()],
                    ScheduleGroupType::cases(),
                ),
            ],
        ]);
    }

    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetView), 403);

        [$unit, $engine, $date] = $this->resolveTarget($request);

        $data = $this->builder->build($unit, $engine, $date);
        $record = $this->currentRecord($unit->id, $engine->id, $date);
        $grid = $record?->content_grid ?? $this->builder->grid($data);

        return Inertia::render('operator/laporan/document', [
            'filters' => ['unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => $date],
            'document_number' => $data['document_number'],
            'content' => $record?->content_html ?? $this->builder->bodyHtml($data),
            'content_styles' => $this->builder->contentStyles(),
            'letterhead' => $this->builder->letterhead($data),
            'grid' => $grid,
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('operator.laporan.logsheet.pdf', [
                'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => $date,
            ]),
            'can_write' => $user->hasPermissionTo(PermissionName::OperatorLogsheetWrite),
        ]);
    }

    public function pdf(Request $request, OrientationPdfMerger $merger): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetView), 403);

        [$unit, $engine, $date] = $this->resolveTarget($request);

        $data = $this->builder->build($unit, $engine, $date);
        $record = $this->currentRecord($unit->id, $engine->id, $date);
        $styles = $this->builder->contentStyles();

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            // Excel mode: single landscape page (kop + the edited grid table).
            $body = $this->embedAssets($this->builder->letterhead($data).$this->builder->gridToHtml($record->content_grid));
            $pdf = $merger->render($styles, $body, [['show' => '', 'orientation' => 'landscape']], [], self::FOOTER);
        } else {
            // Text mode: cover/info portrait, the wide input table landscape,
            // summary portrait — merged into one PDF (dompdf can't mix per-page).
            $body = $this->embedAssets($record?->content_html ?? $this->builder->bodyHtml($data));
            $pdf = $merger->render($styles, $body, [
                ['show' => 'seg-a', 'orientation' => 'portrait'],
                ['show' => 'seg-b', 'orientation' => 'landscape'],
                ['show' => 'seg-c', 'orientation' => 'portrait'],
            ], ['seg-a', 'seg-b', 'seg-c'], self::FOOTER);
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="Logsheet-'.$unit->id.'-'.$engine->id.'-'.$date.'.pdf"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->persist($request, false);
    }

    /** Rebuild the document from the latest data & template, discarding manual edits. */
    public function regenerate(Request $request): RedirectResponse
    {
        return $this->persist($request, true);
    }

    private function persist(Request $request, bool $regenerate): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetWrite), 403);

        [$unit, $engine, $date] = $this->resolveTarget($request);
        $data = $this->builder->build($unit, $engine, $date);

        $validated = $regenerate ? ['format' => 'html'] : $request->validate([
            'format' => ['required', Rule::in(['html', 'grid'])],
            'content_html' => ['required_if:format,html', 'nullable', 'string'],
            'content_grid' => ['required_if:format,grid', 'nullable', 'array'],
        ]);

        // Match on the date via whereDate: the `date` column is stored with a
        // time component in SQLite, so an equality firstOrNew would miss it and
        // insert a duplicate (violating the unique key).
        $record = OperatorLogsheetDocument::query()
            ->where('unit_id', $unit->id)->where('engine_id', $engine->id)
            ->whereDate('log_date', $date)->first()
            ?? new OperatorLogsheetDocument(['unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => $date]);
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
            ($regenerate ? 'Memuat ulang' : 'Menyimpan')." Dokumen Logsheet {$engine->name} {$date}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $regenerate ? 'Dokumen dimuat ulang dari data terbaru.' : 'Dokumen logsheet disimpan.']);

        return back();
    }

    /**
     * @return array{0: Unit, 1: Machine, 2: string}
     */
    private function resolveTarget(Request $request): array
    {
        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($request->user()->canAccessUnit($unit), 403);

        $engine = Machine::query()->where('unit_id', $unit->id)->findOrFail($request->integer('engine_id'));
        $date = ($request->date('log_date') ?? Carbon::now())->toDateString();

        return [$unit, $engine, $date];
    }

    private function currentRecord(int $unitId, int $engineId, string $date): ?OperatorLogsheetDocument
    {
        $record = OperatorLogsheetDocument::query()
            ->where('unit_id', $unitId)->where('engine_id', $engineId)
            ->whereDate('log_date', $date)->first();

        return $record !== null && (int) $record->content_version === self::BODY_VERSION ? $record : null;
    }
}
