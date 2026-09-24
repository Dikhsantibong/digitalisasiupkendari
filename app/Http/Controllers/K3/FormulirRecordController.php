<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3FormulirRecord;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\K3\K3FormulirDocumentBuilder;
use App\Support\K3FormulirRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Formulir K3 berbasis lembar yang didefinisikan di K3FormulirRegistry
 * (Sarana Prasarana, Kontrol K3 Mingguan, Pemeliharaan TPS LB3 & Oil Trap).
 */
class FormulirRecordController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly K3FormulirDocumentBuilder $documentBuilder,
    ) {}

    public function index(Request $request, string $form): Response
    {
        $definition = K3FormulirRegistry::get($form);
        $user = $request->user();
        $this->authorizeView($user);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', $request->integer('unit_id')) ?? $units->first();
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year, $week] = $this->period($request, $definition);
        $record = $this->findRecord($unit, $form, $month, $year, $week);
        $content = $this->content($definition, $record);

        $data = $this->documentBuilder->buildData($unit, $month, $year, $definition, $content, $this->documentBuilder->metaInput($record), $week);
        $pdfUrl = route('k3.formulir.record.pdf', ['form' => $form, 'unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'week' => $week]);
        $now = Carbon::now();

        return Inertia::render("k3/formulir/{$form}/index", [
            'form' => [
                'key' => $form,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'period' => $definition['period'],
                'single_table' => $definition['single_table'],
                'header_fields' => $definition['header_fields'],
                'notes_label' => $definition['notes_label'],
                'sign_place' => $definition['sign_place'],
                'sections' => array_map(fn (array $section): array => [
                    'key' => $section['key'],
                    'label' => $section['label'],
                    'letter' => $section['letter'],
                    'addable' => $section['addable'],
                    'columns' => $section['columns'],
                ], $definition['sections']),
            ],
            'unit' => ['id' => $unit->id, 'name' => $unit->name, 'service_unit_name' => $unit->serviceUnit?->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'week' => $week],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'sections' => $content['sections'],
            'header' => $content['header'],
            'catatan' => $record?->catatan ?? '',
            'has_saved' => $record !== null,
            'history' => $this->history($unit, $form),
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
            ...$this->documentBuilder->pageProps($unit, $data, $record, $pdfUrl),
        ]);
    }

    public function store(Request $request, string $form): RedirectResponse
    {
        $definition = K3FormulirRegistry::get($form);
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'week' => ['nullable', 'integer', 'between:0,5'],
            'sections' => ['present', 'array'],
            'sections.*' => ['array'],
            'sections.*.*' => ['array'],
            'sections.*.*.*' => ['nullable', 'string', 'max:1000'],
            'header' => ['nullable', 'array'],
            'header.*' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            ...K3FormulirDocumentBuilder::documentRules(),
        ]);

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $week = $definition['period'] === 'weekly' ? max(1, (int) ($validated['week'] ?? 1)) : 0;

        $headerKeys = array_column($definition['header_fields'], 'key');
        $header = array_map(
            fn (mixed $value): string => trim((string) $value),
            array_intersect_key($validated['header'] ?? [], array_flip($headerKeys)),
        );

        $record = K3FormulirRecord::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'form' => $form, 'year' => $year, 'month' => $month, 'week' => $week],
            [
                ...$this->documentBuilder->metaAttributes($validated),
                'data' => [
                    'sections' => K3FormulirRegistry::sanitizeSections($definition, $validated['sections']),
                    'header' => $header,
                ],
                'catatan' => $validated['catatan'] ?? null,
                'input_by' => $user->id,
            ],
        );

        $period = K3FormulirRegistry::periodLabel($month, $year, $week);
        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan {$definition['title']} {$unit->name} {$period}",
            $record,
            unit: $unit->id,
        );

        return redirect()
            ->route('k3.formulir.record.index', ['form' => $form, 'unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'week' => $week])
            ->with('toast', ['type' => 'success', 'message' => "{$definition['title']} berhasil disimpan."]);
    }

    public function pdf(Request $request, string $form): HttpResponse
    {
        $definition = K3FormulirRegistry::get($form);
        $user = $request->user();
        $this->authorizeView($user);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year, $week] = $this->period($request, $definition);
        $record = $this->findRecord($unit, $form, $month, $year, $week);
        $input = $this->documentBuilder->applyPageOverrides($this->documentBuilder->metaInput($record), $request);

        $data = $this->documentBuilder->buildData($unit, $month, $year, $definition, $this->content($definition, $record), $input, $week);
        $html = $this->documentBuilder->renderHtml($data, $record?->format === 'html' ? $record->content_html : null);

        $filename = sprintf('%s-%s-%04d%02d%s.pdf', $form, str($unit->name)->slug(), $year, $month, $week > 0 ? "-M{$week}" : '');
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response(Pdf::loadHTML($html)->setPaper('a4', $definition['orientation'])->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    private function authorizeView(User $user): void
    {
        abort_unless(
            $user->hasPermissionTo(PermissionName::K3InputView) ||
            $user->hasPermissionTo(PermissionName::K3LaporanView),
            403
        );
    }

    /**
     * Periode dari query (minggu hanya untuk formulir mingguan; 0 = bulanan).
     *
     * @param  array{period: string}  $definition
     * @return array{0: int, 1: int, 2: int}
     */
    private function period(Request $request, array $definition): array
    {
        $now = Carbon::now();
        $month = max(1, min(12, $request->integer('month') ?: (int) $now->month));
        $year = $request->integer('year') ?: (int) $now->year;
        $week = $definition['period'] === 'weekly'
            ? max(1, min(5, $request->integer('week') ?: (int) ceil($now->day / 7)))
            : 0;

        return [$month, $year, $week];
    }

    private function findRecord(Unit $unit, string $form, int $month, int $year, int $week): ?K3FormulirRecord
    {
        return K3FormulirRecord::query()
            ->where('unit_id', $unit->id)
            ->where('form', $form)
            ->where('year', $year)
            ->where('month', $month)
            ->where('week', $week)
            ->first();
    }

    /**
     * Isi tersimpan, atau template default formulir bila belum ada.
     *
     * @param  array<string, mixed>  $definition
     * @return array{form: array<string, mixed>, sections: array<string, list<array<string, string>>>, header: array<string, string>}
     */
    private function content(array $definition, ?K3FormulirRecord $record): array
    {
        $sections = $record
            ? K3FormulirRegistry::sanitizeSections($definition, $record->data['sections'] ?? [])
            : K3FormulirRegistry::defaultSections($definition);

        $header = [];
        foreach ($definition['header_fields'] as $field) {
            $header[$field['key']] = (string) ($record?->data['header'][$field['key']] ?? '');
        }

        return ['form' => $definition, 'sections' => $sections, 'header' => $header];
    }

    /**
     * Periode yang pernah disimpan untuk formulir ini di unit ini (terbaru dulu).
     *
     * @return list<array{year: int, month: int, week: int, label: string, updated_at: string|null}>
     */
    private function history(Unit $unit, string $form): array
    {
        return K3FormulirRecord::query()
            ->where('unit_id', $unit->id)
            ->where('form', $form)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('week')
            ->limit(30)
            ->get(['year', 'month', 'week', 'format', 'updated_at'])
            ->map(fn (K3FormulirRecord $record): array => [
                'year' => $record->year,
                'month' => $record->month,
                'week' => $record->week,
                'label' => K3FormulirRegistry::periodLabel($record->month, $record->year, $record->week),
                'format' => $record->format,
                'updated_at' => $record->updated_at?->toDateTimeString(),
            ])
            ->all();
    }
}
