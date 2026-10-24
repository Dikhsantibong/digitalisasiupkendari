<?php

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Models\HarUnsafeCondition;
use App\Models\Unit;
use App\Models\User;
use App\Services\Notifications\EventNotifier;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saves the Unsafe Action & Unsafe Condition sheet of a month at once — the
 * table filled row by row ("Tambah Baris" + Simpan) on the Operasi and HAR
 * pages, which share har_unsafe_conditions.
 *
 * - A row with an id updates that finding of the unit & month; a row without
 *   one is a new finding (blank rows are skipped). Only the ids listed in
 *   `deleted_ids` are deleted, so a finding a colleague added meanwhile is
 *   never lost.
 * - A photo is kept only as the path the finding already has; a new upload
 *   replaces it (the old file is deleted), an empty value removes it.
 * - New findings are announced ({@see EventNotifier::unsafeFound}) and a
 *   finding closed in this save tells its reporter.
 */
class UnsafeConditionSync
{
    public const KATEGORI = ['UNSAFE ACTION', 'UNSAFE CONDITION'];

    /** The photo columns of a finding. */
    private const PHOTOS = ['foto_sebelum', 'foto_sesudah'];

    private const TEXTS = ['periode', 'temuan', 'kondisi', 'tindak_lanjut', 'rekomendasi', 'lokasi'];

    public function __construct(
        private readonly EventNotifier $events,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * @return array{created: int, updated: int, deleted: int}
     */
    public function save(Request $request, Unit $unit, string $module, User $user): array
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['present', 'array', 'max:300'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.periode' => ['nullable', 'string', 'max:50'],
            'rows.*.kategori' => ['nullable', Rule::in(self::KATEGORI)],
            'rows.*.temuan' => ['nullable', 'string', 'max:5000'],
            'rows.*.kondisi' => ['nullable', 'string', 'max:255'],
            'rows.*.tindak_lanjut' => ['nullable', 'string', 'max:5000'],
            'rows.*.rekomendasi' => ['nullable', 'string', 'max:5000'],
            'rows.*.lokasi' => ['nullable', 'string', 'max:255'],
            'rows.*.keterangan' => ['nullable', Rule::in(['open', 'close'])],
            'rows.*.foto_sebelum' => ['nullable', 'string', 'max:255'],
            'rows.*.foto_sesudah' => ['nullable', 'string', 'max:255'],
            'rows.*.foto_sebelum_file' => ['nullable', 'image', 'max:10240'],
            'rows.*.foto_sesudah_file' => ['nullable', 'image', 'max:10240'],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => ['integer'],
        ]);

        // validated() lists rows in the order its rules first met them; keep the sheet's order.
        $rows = $validated['rows'];
        ksort($rows);
        $validated['rows'] = $rows;

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $existing = HarUnsafeCondition::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->get()->keyBy('id');

        // A finding needs its temuan; an untouched blank new row is skipped.
        $errors = [];
        foreach ($validated['rows'] as $index => $row) {
            $id = isset($row['id']) ? (int) $row['id'] : null;
            if ($id !== null && ! $existing->has($id)) {
                $errors["rows.{$index}.id"] = 'Temuan ini tidak ada di unit & bulan tersebut. Muat ulang halaman.';
            }
            if (trim((string) ($row['temuan'] ?? '')) === '' && ($id !== null || $this->hasContent($request, $row, $index))) {
                $errors["rows.{$index}.temuan"] = 'Temuan baris '.($index + 1).' wajib diisi.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $created = [];
        $closed = [];
        $updated = 0;
        $deleted = 0;
        $oldFiles = [];

        DB::transaction(function () use ($validated, $request, $unit, $month, $year, $module, $user, $existing, &$created, &$closed, &$updated, &$deleted, &$oldFiles): void {
            $sort = 0;
            $kept = [];

            foreach ($validated['rows'] as $index => $row) {
                $id = isset($row['id']) ? (int) $row['id'] : null;
                if ($id === null && trim((string) ($row['temuan'] ?? '')) === '') {
                    continue;
                }

                $record = $id !== null ? $existing->get($id) : null;
                $values = [
                    ...collect(self::TEXTS)->mapWithKeys(fn (string $key): array => [$key => trim((string) ($row[$key] ?? '')) ?: null])->all(),
                    'kategori' => $row['kategori'] ?? 'UNSAFE CONDITION',
                    'keterangan' => $row['keterangan'] ?? 'open',
                    'sort_order' => ++$sort,
                ];
                $values['periode'] ??= '-';

                foreach (self::PHOTOS as $column) {
                    $upload = $request->file("rows.{$index}.{$column}_file");
                    $current = $record?->{$column};
                    $values[$column] = $upload instanceof UploadedFile
                        ? $upload->store('har-unsafe', 'public')
                        : (($row[$column] ?? null) !== null && $row[$column] === $current ? $current : null);

                    if ($current !== null && $values[$column] !== $current) {
                        $oldFiles[] = $current;
                    }
                }

                if ($record !== null) {
                    $wasOpen = strtolower((string) $record->keterangan) !== 'close';
                    $record->update($values);
                    $kept[] = $record->id;
                    $updated++;
                    if ($wasOpen && $record->keterangan === 'close') {
                        $closed[] = $record;
                    }
                } else {
                    $created[] = HarUnsafeCondition::query()->create([
                        ...$values,
                        'unit_id' => $unit->id,
                        'source' => $module,
                        'year' => $year,
                        'month' => $month,
                        'input_by' => $user->id,
                    ]);
                }
            }

            foreach (array_unique(array_map('intval', (array) ($validated['deleted_ids'] ?? []))) as $id) {
                $record = $existing->get($id);
                if ($record === null || in_array($id, $kept, true)) {
                    continue;
                }
                $oldFiles = [...$oldFiles, ...array_filter([$record->foto_sebelum, $record->foto_sesudah])];
                $record->delete();
                $deleted++;
            }
        });

        // Files of replaced / removed photos and deleted findings, once the save stands.
        Storage::disk('public')->delete(array_values(array_unique($oldFiles)));

        foreach ($created as $record) {
            $this->events->unsafeFound($record, $module, $user);
        }
        foreach ($closed as $record) {
            $this->events->unsafeClosed($record, $module, $user);
        }

        $this->activityLogger->log(
            ActivityEvent::Updated,
            'Menyimpan laporan unsafe action/condition '.$unit->name." {$month}/{$year} (".($module === 'har' ? 'Pemeliharaan' : 'Operasi').'): '
                .count($created).' baru, '.$updated.' diperbarui, '.$deleted.' dihapus',
            $unit,
            unit: $unit->id,
        );

        return ['created' => count($created), 'updated' => $updated, 'deleted' => $deleted];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasContent(Request $request, array $row, int $index): bool
    {
        foreach (['kondisi', 'tindak_lanjut', 'rekomendasi', 'lokasi'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return true;
            }
        }

        return $request->hasFile("rows.{$index}.foto_sebelum_file") || $request->hasFile("rows.{$index}.foto_sesudah_file");
    }
}
