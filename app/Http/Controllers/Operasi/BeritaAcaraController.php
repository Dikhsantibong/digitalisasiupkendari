<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\BeritaAcaraType;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Controller;
use App\Models\DocumentRecord;
use App\Models\Employee;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Operasi\BeritaAcaraBuilder;
use App\Services\Operasi\DocumentGridBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu Berita Acara (modul OPERASI).
 */
class BeritaAcaraController extends Controller
{
    use EmbedsReportLogo;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly BeritaAcaraBuilder $builder,
        private readonly DocumentGridBuilder $gridBuilder,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiBeritaAcaraView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();

        return Inertia::render('operasi/berita-acara/index', [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => (int) ($request->integer('month') ?: $now->month),
                'year' => (int) ($request->integer('year') ?: $now->year),
            ],
            'types' => collect(BeritaAcaraType::cases())
                ->map(fn (BeritaAcaraType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'title' => $type->documentTitle(),
                ])
                ->all(),
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'can_create' => $user->hasPermissionTo(PermissionName::OperasiBeritaAcaraCreate),
        ]);
    }

    public function show(Request $request, string $type): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiBeritaAcaraView), 403);

        $beritaAcaraType = BeritaAcaraType::tryFrom($type);
        abort_if($beritaAcaraType === null, 404);

        $units = Unit::query()->visibleTo($user)->with('serviceUnit:id,name')->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];
        if ($month < 1 || $month > 12) {
            $month = (int) Carbon::now()->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) Carbon::now()->year;
        }

        $data = $this->builder->build($unit, $beritaAcaraType, $month, $year);
        $record = $this->existingRecord($unit->id, $beritaAcaraType, $month, $year);

        $mergedData = $data;
        if ($record !== null && ! empty($record->snapshot)) {
            $mergedData = array_replace_recursive($data, $record->snapshot);
            if (isset($record->snapshot['pemakaian'])) {
                $mergedData['pemakaian'] = $record->snapshot['pemakaian'];
            }
            if (isset($record->snapshot['fisik'])) {
                $mergedData['fisik'] = $record->snapshot['fisik'];
            }
            if (isset($record->snapshot['rows'])) {
                $mergedData['rows'] = $record->snapshot['rows'];
            }
        }

        $managerOptions = $this->resolveManagerOptions($unit);
        $tlOptions = $this->resolveTlOptions($unit);

        return Inertia::render('operasi/berita-acara/editor', [
            'type' => ['value' => $beritaAcaraType->value, 'label' => $beritaAcaraType->label()],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_id' => $unit->service_unit_id,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'units' => $units->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all(),
            'document_number' => $mergedData['document']['number'],
            'data' => $mergedData,
            'form_data' => $mergedData,
            'content' => $record?->content_html ?? $this->renderBody($beritaAcaraType, $mergedData),
            'content_styles' => view('operasi.berita-acara.partials.ba-styles')->render(),
            'letterhead' => view('operasi.berita-acara.partials.letterhead', [
                'document' => $mergedData['document'],
            ])->render(),
            'grid' => $record?->content_grid ?? $this->gridBuilder->forBeritaAcara($beritaAcaraType, $mergedData),
            'format' => $record?->format ?? 'form',
            'has_saved' => $record !== null,
            'manager_options' => $managerOptions,
            'tl_options' => $tlOptions,
            'pdf_url' => route('operasi.berita-acara.pdf', [
                'type' => $beritaAcaraType->value,
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ]),
            'can_create' => $user->hasPermissionTo(PermissionName::OperasiBeritaAcaraCreate),
        ]);
    }

    public function preview(Request $request, string $type): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiBeritaAcaraView), 403);

        $beritaAcaraType = BeritaAcaraType::tryFrom($type);
        abort_if($beritaAcaraType === null, 404);

        $unit = $this->resolveUnit($request);
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];

        $now = Carbon::now();
        $month = $month ?: $now->month;
        $year = $year ?: $now->year;

        $record = $this->existingRecord($unit->id, $beritaAcaraType, $month, $year);
        $monthName = Carbon::create($year, $month, 1)->locale('id')->isoFormat('MMMM');

        return Inertia::render('operasi/berita-acara/preview', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_id' => $unit->service_unit_id,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'type' => [
                'value' => $beritaAcaraType->value,
                'label' => $beritaAcaraType->label(),
                'title' => $beritaAcaraType->documentTitle(),
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'month_name' => $monthName,
            'pdf_url' => route('operasi.berita-acara.pdf', [
                'type' => $beritaAcaraType->value,
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ]),
            'has_saved' => $record !== null,
            'can_create' => $user->hasPermissionTo(PermissionName::OperasiBeritaAcaraCreate),
        ]);
    }

    public function pdf(Request $request, string $type)
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiBeritaAcaraView), 403);

        $beritaAcaraType = BeritaAcaraType::tryFrom($type);
        abort_if($beritaAcaraType === null, 404);

        $unit = $this->resolveUnit($request);
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];

        $record = $this->existingRecord($unit->id, $beritaAcaraType, $month, $year);

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            // Excel mode: the letterhead (logo + org + document box) is added
            // here — a spreadsheet cannot hold it — above the edited grid body.
            $data = $record->snapshot ?? $this->builder->build($unit, $beritaAcaraType, $month, $year);
            $letterhead = view('operasi.berita-acara.partials.letterhead', [
                'document' => $data['document'],
            ])->render();
            $content = $letterhead.$this->gridBuilder->gridToHtml($record->content_grid);
        } else {
            // Form or HTML mode
            $content = $record?->content_html
                ?? $this->renderBody($beritaAcaraType, $this->builder->build($unit, $beritaAcaraType, $month, $year));
        }

        $pdf = Pdf::loadView('operasi.berita-acara.pdf-shell', [
            'content' => $this->embedAssets($content),
            'margin_top' => $request->input('page_margin_top', 15),
            'margin_bottom' => $request->input('page_margin_bottom', 15),
            'margin_left' => $request->input('page_margin_left', 15),
            'margin_right' => $request->input('page_margin_right', 15),
            'line_spacing' => $request->input('line_spacing', '1.15'),
        ])->setPaper('a4');
        $filename = "BA-{$beritaAcaraType->value}-{$unit->id}-{$month}-{$year}.pdf";

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiBeritaAcaraCreate), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'type' => ['required', 'string'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'format' => ['required', Rule::in(['form', 'html', 'grid'])],
            'form_data' => ['nullable', 'array'],
            'content_html' => ['required_if:format,html', 'nullable', 'string'],
            'content_grid' => ['required_if:format,grid', 'nullable', 'array'],
        ]);

        $type = BeritaAcaraType::tryFrom($validated['type']);
        abort_if($type === null, 404);

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        $data = $this->builder->build($unit, $type, $month, $year);
        if (! empty($validated['form_data'])) {
            $data = array_replace_recursive($data, $validated['form_data']);
            if (isset($validated['form_data']['pemakaian'])) {
                $data['pemakaian'] = $validated['form_data']['pemakaian'];
            }
            if (isset($validated['form_data']['fisik'])) {
                $data['fisik'] = $validated['form_data']['fisik'];
            }
            if (isset($validated['form_data']['rows'])) {
                $data['rows'] = $validated['form_data']['rows'];
            }
        }

        $period = ReportPeriod::query()
            ->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();

        $record = DocumentRecord::query()->firstOrNew([
            'unit_id' => $unit->id, 'type' => $type->value, 'month' => $month, 'year' => $year,
        ]);
        if (! $record->exists) {
            $record->created_by = $user->id;
        }
        $record->report_period_id = $period?->id;
        $record->document_number = $data['document']['number'] ?? $record->document_number;
        $record->format = $validated['format'];

        if ($validated['format'] === 'form') {
            $record->content_html = $this->renderBody($type, $data);
        } elseif (array_key_exists('content_html', $validated) && $validated['content_html'] !== null) {
            $record->content_html = $validated['content_html'];
        }

        if (array_key_exists('content_grid', $validated) && $validated['content_grid'] !== null) {
            $record->content_grid = $validated['content_grid'];
        }

        $record->snapshot = $data;
        $record->save();

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menyimpan {$type->label()} {$unit->name} periode {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Berita Acara berhasil disimpan.']);

        return back();
    }

    private function existingRecord(int $unitId, BeritaAcaraType $type, int $month, int $year): ?DocumentRecord
    {
        return DocumentRecord::query()
            ->where('unit_id', $unitId)
            ->where('type', $type->value)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderBody(BeritaAcaraType $type, array $data): string
    {
        $view = $type->isFuel()
            ? 'operasi.berita-acara.partials.bbm'
            : 'operasi.berita-acara.partials.pelumas';

        return view($view, ['data' => $data])->render();
    }

    private function resolveUnit(Request $request): Unit
    {
        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($request->user()->canAccessUnit($unit), 403);

        return $unit;
    }

    /**
     * Resolve Manager options based on Unit & ServiceUnit.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveManagerOptions(Unit $unit): array
    {
        $employees = Employee::query()
            ->where('is_active', true)
            ->where(function ($q) use ($unit) {
                if ($unit->service_unit_id) {
                    $q->where('service_unit_id', $unit->service_unit_id);
                }
                $q->orWhere('position', 'like', '%manager%')
                    ->orWhere('position', 'like', '%manajer%');
            })
            ->orderBy('name')
            ->get();

        return $employees->map(fn (Employee $e) => [
            'id' => $e->id,
            'name' => $e->name,
            'nip' => $e->nip,
            'position' => $e->position,
            'has_signature' => ! empty($e->signature_path),
            'signature_url' => $e->signatureUrl(),
        ])->all();
    }

    /**
     * Resolve TL Operasi options based on Unit.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveTlOptions(Unit $unit): array
    {
        $employees = Employee::query()
            ->where('is_active', true)
            ->where(function ($q) use ($unit) {
                $q->where('unit_id', $unit->id);
                if ($unit->service_unit_id) {
                    $q->orWhere('service_unit_id', $unit->service_unit_id);
                }
            })
            ->orderByRaw("CASE WHEN position LIKE '%operasi%' OR position LIKE '%tl%' OR position LIKE '%team leader%' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return $employees->map(fn (Employee $e) => [
            'id' => $e->id,
            'name' => $e->name,
            'nip' => $e->nip,
            'position' => $e->position,
            'has_signature' => ! empty($e->signature_path),
            'signature_url' => $e->signatureUrl(),
        ])->all();
    }
}
