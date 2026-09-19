<?php

namespace App\Services\Pdm;

use App\Models\Machine;
use App\Models\PdmFormDocument;
use App\Models\PdmFormItem;
use App\Models\Unit;
use App\Support\PdmForms\PdmForm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Loads and saves generic PdM form documents ({@see PdmForm}). A loaded
 * document always has every section's rows: saved rows, or the form's default
 * rows, padded with blank rows to the section's minimum; fixed sections keep
 * their default rows and read-only cells.
 */
class PdmFormDocuments
{
    /**
     * @return array{id: int|null, saved: bool, subject: string, header: array<string, mixed>, rows: array<string, list<array<string, string|null>>>, totals: array<string, array<string, string>>, summary: array<string, mixed>|null, images: array<string, list<array{path: string, url: string}>>}
     */
    public function load(PdmForm $form, Unit $unit, int $month, int $year, string $subject = '', ?Machine $machine = null, bool $embedImages = false): array
    {
        $document = PdmFormDocument::query()->with('items')
            ->where('unit_id', $unit->id)->where('form', $form->key())
            ->where('year', $year)->where('month', $month)->where('subject', $subject)
            ->first();

        return $this->payload($form, $unit, $month, $year, $document, $machine, $embedImages, $subject);
    }

    /**
     * Every saved document of a form for the period (per machine for
     * per-machine forms), as loaded payloads with the machine name.
     *
     * @return list<array<string, mixed>>
     */
    public function saved(PdmForm $form, Unit $unit, int $month, int $year, bool $embedImages = false): array
    {
        return PdmFormDocument::query()->with('items')
            ->where('unit_id', $unit->id)->where('form', $form->key())
            ->where('year', $year)->where('month', $month)
            ->orderBy('subject')->get()
            ->map(function (PdmFormDocument $document) use ($form, $unit, $month, $year, $embedImages): array {
                $machine = $document->subject !== '' ? Machine::query()->find((int) $document->subject) : null;

                return $this->payload($form, $unit, $month, $year, $document, $machine, $embedImages, $document->subject)
                    + ['machine_name' => $machine?->name];
            })->all();
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, list<array<string, mixed>>>  $rows
     * @param  array<string, list<UploadedFile>>  $uploads
     */
    public function save(PdmForm $form, Unit $unit, int $month, int $year, string $subject, array $header, array $rows, array $uploads, int $userId): PdmFormDocument
    {
        return DB::transaction(function () use ($form, $unit, $month, $year, $subject, $header, $rows, $uploads, $userId): PdmFormDocument {
            $document = PdmFormDocument::query()->firstOrNew([
                'unit_id' => $unit->id, 'form' => $form->key(), 'year' => $year, 'month' => $month, 'subject' => $subject,
            ]);

            $clean = [];
            foreach ($form->fields() as $field) {
                $clean[$field['key']] = ($field['type'] ?? 'text') === 'images'
                    ? $this->images($form, $unit, (array) ($header[$field['key']] ?? []), $uploads[$field['key']] ?? [])
                    : $this->string($header[$field['key']] ?? null);
            }

            $document->header = $clean;
            $document->input_by = $userId;
            $document->save();
            $document->items()->delete();

            foreach ($form->sections() as $section) {
                $defaults = array_values($section['rows'] ?? []);
                $order = 0;
                foreach (array_values($rows[$section['key']] ?? []) as $index => $row) {
                    $data = [];
                    foreach ($section['columns'] as $column) {
                        $data[$column['key']] = ($column['type'] ?? 'text') === 'readonly'
                            ? $this->string($defaults[$index][$column['key']] ?? null)
                            : $this->string($row[$column['key']] ?? null);
                    }

                    if (empty($section['fixed']) && $this->isBlank($section, $data)) {
                        continue;
                    }

                    PdmFormItem::query()->create([
                        'pdm_form_document_id' => $document->id,
                        'unit_id' => $unit->id,
                        'section' => $section['key'],
                        'data' => $data,
                        'sort_order' => $order++,
                    ]);
                }
            }

            return $document;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PdmForm $form, Unit $unit, int $month, int $year, ?PdmFormDocument $document, ?Machine $machine, bool $embedImages, string $subject): array
    {
        $header = array_merge(
            $form->defaults($unit, $month, $year, $machine),
            array_filter((array) ($document?->header ?? []), fn ($value): bool => $value !== null),
        );

        $rows = [];
        $totals = [];
        foreach ($form->sections() as $section) {
            $key = $section['key'];
            $defaults = array_values($section['rows'] ?? []);
            $saved = ($document?->items ?? collect())->where('section', $key)->values()
                ->map(fn (PdmFormItem $item): array => $this->row($section, $item->data))->all();

            if (! empty($section['fixed'])) {
                $list = array_map(fn (array $default, int $i): array => array_merge(
                    $this->row($section, $saved[$i] ?? []),
                    array_filter($default, fn ($v, string $col): bool => $this->type($section, $col) === 'readonly', ARRAY_FILTER_USE_BOTH),
                ), $defaults, array_keys($defaults));
            } else {
                $list = $document !== null ? $saved : array_map(fn (array $default): array => $this->row($section, $default), $defaults);
                $minimum = max((int) ($section['blank_rows'] ?? 0), count($list));
                while (count($list) < $minimum) {
                    $list[] = $this->row($section, []);
                }
            }

            $rows[$key] = $list;
            foreach ($section['totals'] ?? [] as $column) {
                $sum = array_sum(array_map(fn (array $row): float => is_numeric($row[$column] ?? null) ? (float) $row[$column] : 0.0, $list));
                $totals[$key][$column] = rtrim(rtrim(number_format($sum, 2, '.', ''), '0'), '.');
            }
        }

        $images = [];
        foreach ($form->fields() as $field) {
            if (($field['type'] ?? '') !== 'images') {
                continue;
            }
            $images[$field['key']] = array_values(array_filter(array_map(function ($path) use ($embedImages): ?array {
                $disk = Storage::disk('public');
                if (! is_string($path) || ! $disk->exists($path)) {
                    return null;
                }

                return [
                    'path' => $path,
                    'url' => $embedImages
                        ? 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode((string) $disk->get($path))
                        : $disk->url($path),
                ];
            }, (array) ($header[$field['key']] ?? []))));
        }

        return [
            'id' => $document?->id,
            'saved' => $document !== null,
            'subject' => $subject,
            'header' => $header,
            'rows' => $rows,
            'totals' => $totals,
            'summary' => $form->summary($rows),
            'images' => $images,
        ];
    }

    /**
     * Keep the kept images of this form & unit and store the new uploads.
     *
     * @param  list<mixed>  $kept
     * @param  list<UploadedFile>  $uploads
     * @return list<string>
     */
    private function images(PdmForm $form, Unit $unit, array $kept, array $uploads): array
    {
        $prefix = "pdm/{$form->key()}/{$unit->id}/";
        $paths = array_values(array_filter($kept, fn ($path): bool => is_string($path) && str_starts_with($path, $prefix)));
        foreach ($uploads as $file) {
            $paths[] = $file->store(rtrim($prefix, '/'), 'public');
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function row(array $section, array $data): array
    {
        $row = [];
        foreach ($section['columns'] as $column) {
            $row[$column['key']] = $this->string($data[$column['key']] ?? null);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, string|null>  $data
     */
    private function isBlank(array $section, array $data): bool
    {
        foreach ($section['columns'] as $column) {
            if (($column['type'] ?? 'text') !== 'readonly' && ($data[$column['key']] ?? null) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function type(array $section, string $column): string
    {
        foreach ($section['columns'] as $spec) {
            if ($spec['key'] === $column) {
                return $spec['type'] ?? 'text';
            }
        }

        return 'text';
    }

    private function string(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
