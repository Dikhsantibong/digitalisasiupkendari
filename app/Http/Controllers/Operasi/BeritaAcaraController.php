<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\BeritaAcaraType;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\DocumentRecord;
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
 * Menu Berita Acara (modul OPERASI). A document is generated with its numbers
 * filled in, opened in a Word-like editor for the user to fine-tune the wording
 * or figures, saved as editable HTML, and exported to PDF (dompdf) from exactly
 * that edited content. The letter number is fixed per template.
 */
class BeritaAcaraController extends Controller
{
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

        $unit = $this->resolveUnit($request);
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];

        $data = $this->builder->build($unit, $beritaAcaraType, $month, $year);
        $record = $this->existingRecord($unit->id, $beritaAcaraType, $month, $year);

        return Inertia::render('operasi/berita-acara/editor', [
            'type' => ['value' => $beritaAcaraType->value, 'label' => $beritaAcaraType->label()],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'document_number' => $data['document']['number'],
            'content' => $record?->content_html ?? $this->renderBody($beritaAcaraType, $data),
            'content_styles' => view('operasi.berita-acara.partials.ba-styles')->render(),
            'letterhead' => view('operasi.berita-acara.partials.letterhead', [
                'document' => $data['document'],
            ])->render(),
            'grid' => $record?->content_grid ?? $this->gridBuilder->forBeritaAcara($beritaAcaraType, $data),
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('operasi.berita-acara.pdf', [
                'type' => $beritaAcaraType->value,
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ]),
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
            $data = $this->builder->build($unit, $beritaAcaraType, $month, $year);
            $letterhead = view('operasi.berita-acara.partials.letterhead', [
                'document' => $data['document'],
            ])->render();
            $content = $letterhead.$this->gridBuilder->gridToHtml($record->content_grid);
        } else {
            // Text mode: the body already contains the letterhead inline.
            $content = $record?->content_html
                ?? $this->renderBody($beritaAcaraType, $this->builder->build($unit, $beritaAcaraType, $month, $year));
        }

        $pdf = Pdf::loadView('operasi.berita-acara.pdf-shell', [
            'content' => $this->embedAssets($content),
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
            'format' => ['required', Rule::in(['html', 'grid'])],
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
        $period = ReportPeriod::query()
            ->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();

        $record = DocumentRecord::query()->firstOrNew([
            'unit_id' => $unit->id, 'type' => $type->value, 'month' => $month, 'year' => $year,
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
        $record->snapshot = $data;
        $record->save();

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menyimpan {$type->label()} {$unit->name} periode {$month}/{$year}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Berita Acara disimpan.']);

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
}
