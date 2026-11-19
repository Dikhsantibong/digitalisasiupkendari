<?php

namespace App\Services\Logistik;

use App\Models\LogistikFormRow;
use App\Models\Unit;
use App\Support\LogistikForms\LogistikForm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Reading and saving the rows of a {@see LogistikForm} table (logistik_form_rows)
 * per unit & month — shared by the Logistik & Gudang inputs (Logistik\FormController)
 * and the Input Logistik of Pemeliharaan Akses 2 (Har\InputLogistikController).
 */
class LogistikFormRows
{
    /**
     * @return Builder<LogistikFormRow>
     */
    public function query(LogistikForm $definition, Unit $unit, int $month, int $year): Builder
    {
        return LogistikFormRow::query()->where('unit_id', $unit->id)->where('form', $definition->key())
            ->where('year', $year)->where('month', $month);
    }

    /**
     * Validate the posted rows and replace the period's rows with them (blank
     * rows are skipped; computed columns are not stored).
     */
    public function save(Request $request, LogistikForm $definition, Unit $unit, int $month, int $year): void
    {
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
    }

    /**
     * Saved rows — or the default rows — per section, padded with blank rows
     * to the form's shape, with computed values and image URLs.
     *
     * @return list<array{section: string, data: array<string, mixed>, image_urls: array<string, string>}>
     */
    public function rows(LogistikForm $definition, Unit $unit, int $month, int $year, bool $embedImages = false): array
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
    public function summary(LogistikForm $definition, Unit $unit, int $month, int $year): ?array
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
