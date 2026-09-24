<?php

namespace App\Http\Controllers\Logistik;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\HandlesLogistikInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\LogistikFormRow;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use App\Support\LogistikForms\LogistikForm;
use App\Support\LogistikForms\LogistikForms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every Logistik & Gudang table form ({@see LogistikForms}): Laporan
 * Pendukung, Peralatan/Material/Tools, Kondisi Stok and Unsafe Action &
 * Condition. Rows are saved per unit & month; before anything is saved the
 * form starts from its default rows. Each section always shows at least its
 * default + blank rows, like the paper form. PDF from
 * resources/views/logistik/input/form-pdf.blade.php, Excel from the page.
 */
class FormController extends Controller
{
    use HandlesLogistikInput;
    use RendersReportPdf;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request, string $form): Response
    {
        $definition = $this->definition($form);
        [$units, $unit, $month, $year] = $this->logistikReadTarget($request);

        // Each form has its own page: resources/js/pages/logistik/input/{form}/index.tsx.
        return Inertia::render("logistik/input/{$definition->key()}/index", [
            'form' => $definition->toArray(),
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => $this->logistikFilterOptions($units),
            'rows' => $this->rows($definition, $unit, $month, $year),
            'summary' => $this->summary($definition, $unit, $month, $year),
            'has_saved' => $this->query($definition, $unit, $month, $year)->exists(),
            'can_write' => $request->user()->hasPermissionTo(PermissionName::LogistikInputWrite),
        ]);
    }

    public function store(Request $request, string $form): RedirectResponse
    {
        $definition = $this->definition($form);
        [$unit, $month, $year] = $this->logistikWriteTarget($request);

        $rules = [
            'rows' => ['present', 'array', 'max:300'],
            'rows.*.section' => ['required', Rule::in(array_column($definition->sections(), 'key'))],
            'rows.*.data' => ['nullable', 'array'],
        ];
        foreach ($definition->columns() as $column) {
            $key = "rows.*.data.{$column['key']}";
            $rules[$key] = match ($column['type']) {
                'number' => ['nullable', 'numeric', 'between:-999999999,999999999'],
                'textarea' => ['nullable', 'string', 'max:2000'],
                'select' => ['nullable', Rule::in($column['options'] ?? [])],
                'date' => ['nullable', 'date_format:Y-m-d'],
                'check' => ['nullable', 'in:0,1'],
                'computed' => ['nullable'],
                default => ['nullable', 'string', 'max:255'],
            };
            if ($column['type'] === 'image') {
                $rules["rows.*.files.{$column['key']}"] = ['nullable', 'image', 'max:5120'];
            }
        }
        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $request, $definition, $unit, $month, $year): void {
            $this->query($definition, $unit, $month, $year)->delete();

            foreach (array_values($validated['rows']) as $index => $row) {
                $data = $this->clean($definition, $unit, (array) ($row['data'] ?? []), (array) $request->file("rows.{$index}.files", []));
                if ($data === null) {
                    continue;
                }

                LogistikFormRow::query()->create([
                    'unit_id' => $unit->id,
                    'form' => $definition->key(),
                    'year' => $year,
                    'month' => $month,
                    'section' => $row['section'],
                    'data' => $data,
                    'sort_order' => $index,
                    'input_by' => $request->user()->id,
                ]);
            }
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan {$definition->title()} {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$definition->title()} berhasil disimpan."]);

        return back();
    }

    public function pdf(Request $request, string $form): HttpResponse
    {
        $definition = $this->definition($form);
        [, $unit, $month, $year] = $this->logistikReadTarget($request);
        [$view, $data] = $this->pdfView($definition, $unit, $month, $year);

        $filename = sprintf('%s_%s_%02d_%d.pdf', preg_replace('/[^A-Za-z0-9]+/', '_', $definition->title()), str_replace(' ', '_', $unit->name), $month, $year);

        return $this->streamReportPdf($request, $view, $data, $filename, $definition->orientation());
    }

    /**
     * The PDF view and its data — reusable by the Laporan Logistik.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(LogistikForm $definition, Unit $unit, int $month, int $year): array
    {
        return ['logistik.input.form-pdf', [
            'form' => $definition,
            'unit' => $unit,
            'periodLabel' => Indonesian::monthName($month).' Tahun '.$year,
            'rows' => $this->rows($definition, $unit, $month, $year, embedImages: true),
            'summary' => $this->summary($definition, $unit, $month, $year),
            ...JadwalPdf::logos(),
        ]];
    }

    private function definition(string $form): LogistikForm
    {
        $definition = LogistikForms::find($form);
        abort_if($definition === null, 404);

        return $definition;
    }

    /**
     * @return Builder<LogistikFormRow>
     */
    private function query(LogistikForm $definition, Unit $unit, int $month, int $year): Builder
    {
        return LogistikFormRow::query()->where('unit_id', $unit->id)->where('form', $definition->key())
            ->where('year', $year)->where('month', $month);
    }

    /**
     * Saved rows — or the default rows — per section, padded with blank rows
     * to the form's shape, with computed values and image URLs.
     *
     * @return list<array{section: string, data: array<string, mixed>, image_urls: array<string, string>}>
     */
    private function rows(LogistikForm $definition, Unit $unit, int $month, int $year, bool $embedImages = false): array
    {
        $saved = $this->query($definition, $unit, $month, $year)->orderBy('sort_order')->orderBy('id')->get()->groupBy('section');
        $defaults = collect($definition->columns())->filter(fn (array $c): bool => array_key_exists('default', $c))->mapWithKeys(fn (array $c): array => [$c['key'] => $c['default']])->all();
        $images = array_column(array_filter($definition->columns(), fn (array $c): bool => $c['type'] === 'image'), 'key');

        $rows = [];
        foreach ($definition->sections() as $section) {
            $list = $saved->isEmpty()
                ? array_map(fn (array $data): array => $data + $defaults, $section['rows'])
                : $saved->get($section['key'], collect())->map(fn (LogistikFormRow $r): array => $r->data)->values()->all();

            for ($n = count($list), $min = count($section['rows']) + $section['blank_rows']; $n < $min; $n++) {
                $list[] = [];
            }

            foreach ($list as $data) {
                $rows[] = [
                    'section' => $section['key'],
                    'data' => $definition->compute($data),
                    'image_urls' => collect($images)
                        ->mapWithKeys(fn (string $key): array => [$key => is_string($data[$key] ?? null) ? $this->imageUrl($data[$key], $embedImages) : null])
                        ->filter()->all(),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string|int|float>>}|null
     */
    private function summary(LogistikForm $definition, Unit $unit, int $month, int $year): ?array
    {
        $bySection = collect($this->rows($definition, $unit, $month, $year))
            ->groupBy('section')
            ->map(fn ($rows): array => $rows->pluck('data')->all())
            ->all();

        return $definition->summary($bySection);
    }

    /**
     * The row's values without computed columns (recomputed on read), kept
     * image paths of this form's folder and new uploads — or null when the
     * row is blank.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile>  $files
     * @return array<string, mixed>|null
     */
    private function clean(LogistikForm $definition, Unit $unit, array $data, array $files): ?array
    {
        $folder = "logistik/form/{$definition->key()}/{$unit->id}";
        $clean = [];
        $filled = false;
        $ticked = [];

        foreach ($definition->columns() as $column) {
            $key = $column['key'];
            $value = $data[$key] ?? null;

            if ($column['type'] === 'computed') {
                continue;
            }

            if ($column['type'] === 'image') {
                $value = isset($files[$key]) && $files[$key] instanceof UploadedFile
                    ? $files[$key]->store($folder, 'public')
                    : (is_string($value) && str_starts_with($value, $folder.'/') ? $value : null);
                $filled = $filled || $value !== null;
            } elseif ($column['type'] === 'check') {
                // One tick per `exclusive` group (e.g. Open / Close): the first one wins.
                $group = $column['exclusive'] ?? null;
                $value = (string) $value === '1' && ($group === null || ! isset($ticked[$group])) ? 1 : null;
                if ($value !== null && $group !== null) {
                    $ticked[$group] = true;
                }
                $filled = $filled || $value !== null;
            } elseif ($column['type'] === 'number') {
                $value = is_numeric($value) ? $value + 0 : null;
                $filled = $filled || ($value !== null && $value != 0 && $value != ($column['default'] ?? null));
            } else {
                $value = trim((string) $value);
                $value = $value === '' ? null : $value;
                $filled = $filled || ($value !== null && ! ($column['type'] === 'select' && $this->isDefaultSelect($definition, $key, $value)));
            }

            $clean[$key] = $value;
        }

        return $filled ? $clean : null;
    }

    /**
     * A select still holding a default-row value (e.g. the preset periode /
     * kategori of an empty temuan row) does not make the row filled.
     */
    private function isDefaultSelect(LogistikForm $definition, string $key, string $value): bool
    {
        return collect($definition->sections())->flatMap(fn (array $s): array => $s['rows'])
            ->contains(fn (array $row): bool => ($row[$key] ?? null) === $value);
    }

    private function imageUrl(string $path, bool $embed): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        return $embed
            ? 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode((string) $disk->get($path))
            : $disk->url($path);
    }
}
