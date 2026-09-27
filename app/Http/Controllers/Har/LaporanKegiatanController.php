<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HarLaporanKegiatan;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akses 2 — Pengusahaan: Laporan Kegiatan Pemeliharaan Mesin Pembangkit and
 * Laporan Kegiatan Pemeliharaan Listrik & Kontrol Pembangkit
 * (FMKD-314-10.3.3-A3). A report month holds numbered entries; saving a report
 * replaces its entries for the unit & month.
 */
class LaporanKegiatanController extends Controller
{
    private const DOCUMENT = ['number' => 'FMKD-314-10.3.3-A3', 'revision' => '01', 'date' => '31 JULI 2024'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarPengusahaanView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        $entries = HarLaporanKegiatan::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('category');

        return Inertia::render('pengusahaan/har/laporan-kegiatan/index', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'reports' => [
                HarLaporanKegiatan::MESIN => $this->entries($entries->get(HarLaporanKegiatan::MESIN)),
                HarLaporanKegiatan::LISTRIK => $this->entries($entries->get(HarLaporanKegiatan::LISTRIK)),
            ],
            'machines' => Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->pluck('name')->all(),
            'document' => self::DOCUMENT,
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::HarPengusahaanWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarPengusahaanWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'category' => ['required', Rule::in([HarLaporanKegiatan::MESIN, HarLaporanKegiatan::LISTRIK])],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'entries' => ['present', 'array', 'max:200'],
            'entries.*.activity_date' => ['nullable', 'date'],
            'entries.*.groups' => ['required', 'array', 'min:1', 'max:20'],
            'entries.*.groups.*.mesin' => ['nullable', 'string', 'max:100'],
            'entries.*.groups.*.judul' => ['nullable', 'string', 'max:255'],
            'entries.*.groups.*.jenis_har' => ['nullable', 'string', 'max:50'],
            'entries.*.groups.*.uraian' => ['nullable', 'array', 'max:50'],
            'entries.*.groups.*.uraian.*' => ['nullable', 'string', 'max:500'],
            'entries.*.hasil_pekerjaan' => ['nullable', 'string', 'max:100'],
            ...collect(['material_nama', 'material_no_part', 'jumlah', 'data', 'no_lh05', 'no_sr_ba', 'no_tug9', 'no_wo_spki'])
                ->mapWithKeys(fn (string $field): array => ["entries.*.{$field}" => ['nullable', 'string', 'max:2000']])
                ->all(),
        ]);

        $category = $validated['category'];
        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $category, $month, $year, $user): void {
            HarLaporanKegiatan::query()
                ->where('unit_id', $unit->id)->where('category', $category)->where('year', $year)->where('month', $month)
                ->delete();

            foreach (array_values($validated['entries']) as $index => $entry) {
                HarLaporanKegiatan::query()->create([
                    'unit_id' => $unit->id,
                    'category' => $category,
                    'year' => $year,
                    'month' => $month,
                    'sort_order' => $index,
                    'activity_date' => $entry['activity_date'] ?? null,
                    'groups' => collect($entry['groups'])->map(fn (array $group): array => [
                        'mesin' => trim((string) ($group['mesin'] ?? '')),
                        'judul' => trim((string) ($group['judul'] ?? '')),
                        'jenis_har' => trim((string) ($group['jenis_har'] ?? '')),
                        'uraian' => collect($group['uraian'] ?? [])
                            ->map(fn ($line): string => trim((string) $line))
                            ->filter(fn (string $line): bool => $line !== '')
                            ->values()
                            ->all(),
                    ])->values()->all(),
                    'hasil_pekerjaan' => $this->text($entry['hasil_pekerjaan'] ?? null),
                    'material_nama' => $this->text($entry['material_nama'] ?? null),
                    'material_no_part' => $this->text($entry['material_no_part'] ?? null),
                    'jumlah' => $this->text($entry['jumlah'] ?? null),
                    'data' => $this->text($entry['data'] ?? null),
                    'no_lh05' => $this->text($entry['no_lh05'] ?? null),
                    'no_sr_ba' => $this->text($entry['no_sr_ba'] ?? null),
                    'no_tug9' => $this->text($entry['no_tug9'] ?? null),
                    'no_wo_spki' => $this->text($entry['no_wo_spki'] ?? null),
                    'input_by' => $user->id,
                ]);
            }
        });

        $label = $category === HarLaporanKegiatan::MESIN ? 'Mesin' : 'Listrik & Kontrol';
        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Laporan Kegiatan Pemeliharaan {$label} {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Laporan Kegiatan Pemeliharaan {$label} disimpan."]);

        return back();
    }

    /**
     * @param  iterable<int, HarLaporanKegiatan>|null  $entries
     * @return list<array<string, mixed>>
     */
    private function entries(?iterable $entries): array
    {
        return collect($entries ?? [])->map(fn (HarLaporanKegiatan $entry): array => [
            'id' => $entry->id,
            'activity_date' => $entry->activity_date?->format('Y-m-d') ?? '',
            'groups' => $entry->groups,
            'hasil_pekerjaan' => (string) $entry->hasil_pekerjaan,
            'material_nama' => (string) $entry->material_nama,
            'material_no_part' => (string) $entry->material_no_part,
            'jumlah' => (string) $entry->jumlah,
            'data' => (string) $entry->data,
            'no_lh05' => (string) $entry->no_lh05,
            'no_sr_ba' => (string) $entry->no_sr_ba,
            'no_tug9' => (string) $entry->no_tug9,
            'no_wo_spki' => (string) $entry->no_wo_spki,
        ])->values()->all();
    }

    private function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
