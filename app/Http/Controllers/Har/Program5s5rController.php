<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HarProgram5s5rEvidence;
use App\Models\HarProgram5s5rItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\HarProgram5s5r;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input Jadwal Program 5S 5R Pemeliharaan (Modul HAR): per minggu lima program
 * (Ringkas … Rajin) dengan kondisi, tindakan, progres, jumlah, keterangan dan
 * foto eviden per minggu. Definisi di App\Support\HarProgram5s5r; hanya baris
 * yang benar-benar diisi yang disimpan (baris bawaan hanya teks program).
 */
class Program5s5rController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeView($user);
        [$units, $unit, $month, $year] = $this->target($request);

        return Inertia::render('har/input/program-5s5r/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name, 'service_unit_name' => $unit->serviceUnit?->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range((int) now()->year - 3, (int) now()->year + 1),
                'programs' => collect(HarProgram5s5r::PROGRAMS)->map(fn (array $p, string $key): array => ['key' => $key, ...$p])->values()->all(),
                'tindakan' => collect(HarProgram5s5r::TINDAKAN)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values()->all(),
                'progres' => HarProgram5s5r::PROGRES,
                'kondisi' => HarProgram5s5r::KONDISI,
                'max_evidence' => HarProgram5s5r::MAX_EVIDENCE,
            ],
            'weeks' => $this->weeks($unit, $month, $year, embed: false),
            'has_saved' => HarProgram5s5rItem::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->exists(),
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $month = max(1, min(12, $request->integer('month')));
        $year = $request->integer('year');
        $weeks = HarProgram5s5r::weeks($month, max(2000, min(2100, $year)));

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['present', 'array', 'max:'.($weeks * count(HarProgram5s5r::PROGRAMS))],
            'rows.*.minggu' => ['required', 'integer', 'between:1,'.$weeks],
            'rows.*.program' => ['required', Rule::in(array_keys(HarProgram5s5r::PROGRAMS))],
            'rows.*.detail' => ['nullable', 'string', 'max:1000'],
            'rows.*.pic' => ['nullable', 'string', 'max:150'],
            'rows.*.kondisi_awal' => ['nullable', Rule::in(HarProgram5s5r::KONDISI)],
            'rows.*.kondisi_akhir' => ['nullable', Rule::in(HarProgram5s5r::KONDISI)],
            'rows.*.progres' => ['nullable', Rule::in(HarProgram5s5r::PROGRES)],
            'rows.*.jumlah' => ['nullable', 'integer', 'between:0,999'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:1000'],
            ...collect(HarProgram5s5r::TINDAKAN)->keys()->mapWithKeys(fn (string $key): array => ["rows.*.{$key}" => ['nullable', 'boolean']])->all(),
            'keep_evidence' => ['nullable', 'array'],
            'keep_evidence.*' => ['nullable', 'array'],
            'keep_evidence.*.*' => ['integer'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['nullable', 'array', 'max:'.HarProgram5s5r::MAX_EVIDENCE],
            'evidence.*.*' => ['image', 'max:5120'],
        ]);

        // Kept + new photos of a week may not exceed the maximum.
        foreach (range(1, $weeks) as $minggu) {
            $total = count((array) ($validated['keep_evidence'][$minggu] ?? [])) + count((array) ($validated['evidence'][$minggu] ?? []));
            if ($total > HarProgram5s5r::MAX_EVIDENCE) {
                throw ValidationException::withMessages([
                    "evidence.{$minggu}" => 'Foto eviden minggu ke '.$minggu.' maksimal '.HarProgram5s5r::MAX_EVIDENCE.' foto.',
                ]);
            }
        }

        DB::transaction(function () use ($validated, $unit, $month, $year, $weeks, $user, $request): void {
            $period = ['unit_id' => $unit->id, 'year' => $year, 'month' => $month];

            foreach ($validated['rows'] as $row) {
                $key = [...$period, 'minggu' => (int) $row['minggu'], 'program' => $row['program']];
                $values = [
                    'detail' => $this->text($row['detail'] ?? null),
                    'pic' => $this->text($row['pic'] ?? null),
                    'kondisi_awal' => $row['kondisi_awal'] ?? null,
                    'progres' => $row['progres'] ?? null,
                    'kondisi_akhir' => $row['kondisi_akhir'] ?? null,
                    'jumlah' => isset($row['jumlah']) ? (int) $row['jumlah'] : null,
                    'keterangan' => $this->text($row['keterangan'] ?? null),
                    ...collect(HarProgram5s5r::TINDAKAN)->keys()->mapWithKeys(fn (string $k): array => [$k => (bool) ($row[$k] ?? false)])->all(),
                ];

                if (HarProgram5s5r::isFilled($values)) {
                    HarProgram5s5rItem::query()->updateOrCreate($key, [...$values, 'input_by' => $user->id]);
                } else {
                    HarProgram5s5rItem::query()->where($key)->delete();
                }
            }

            foreach (range(1, $weeks) as $minggu) {
                $this->syncEvidence(
                    [...$period, 'minggu' => $minggu],
                    array_map('intval', (array) ($validated['keep_evidence'][$minggu] ?? [])),
                    array_values(array_filter((array) $request->file("evidence.{$minggu}", []), fn ($file): bool => $file instanceof UploadedFile)),
                    $user->id,
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Program 5S 5R Pemeliharaan {$unit->name} ".Indonesian::monthName($month)." {$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal Program 5S 5R berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        $this->authorizeView($request->user());
        [, $unit, $month, $year] = $this->target($request);
        [$view, $data] = $this->pdfView($unit, $month, $year);

        $filename = sprintf('Jadwal_Program_5S5R_Pemeliharaan_%s_%02d_%d.pdf', str_replace(' ', '_', $unit->name), $month, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape')->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * The PDF view & data of one month (photos embedded) — reusable by the Laporan HAR.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $unit->loadMissing('serviceUnit');

        return ['har.input.program-5s5r-pdf', [
            'unit' => $unit,
            'periodLabel' => strtoupper(Indonesian::monthName($month)).' '.$year,
            'weeks' => $this->weeks($unit, $month, $year, embed: true),
            'programs' => HarProgram5s5r::PROGRAMS,
            'tindakan' => HarProgram5s5r::TINDAKAN,
            'progres' => HarProgram5s5r::PROGRES,
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * Every week of the month with its five program rows (saved values over
     * the template) and evidence photos.
     *
     * @return list<array{minggu: int, rows: list<array<string, mixed>>, evidence: list<array{id: int, url: string}>}>
     */
    private function weeks(Unit $unit, int $month, int $year, bool $embed): array
    {
        $saved = HarProgram5s5rItem::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->get()
            ->keyBy(fn (HarProgram5s5rItem $item): string => "{$item->minggu}:{$item->program}");
        $evidence = HarProgram5s5rEvidence::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('minggu');

        return array_map(fn (int $minggu): array => [
            'minggu' => $minggu,
            'rows' => array_map(function (string $program) use ($saved, $minggu): array {
                $item = $saved->get("{$minggu}:{$program}");

                return $item === null
                    ? HarProgram5s5r::blankRow($minggu, $program)
                    : [
                        ...HarProgram5s5r::blankRow($minggu, $program),
                        ...$item->only(['detail', 'pic', 'kondisi_awal', 'progres', 'kondisi_akhir', 'jumlah', 'keterangan', ...array_keys(HarProgram5s5r::TINDAKAN)]),
                        'saved' => true,
                    ];
            }, array_keys(HarProgram5s5r::PROGRAMS)),
            'evidence' => ($evidence->get($minggu) ?? collect())->map(fn (HarProgram5s5rEvidence $photo): array => [
                'id' => $photo->id,
                'url' => $embed ? $this->embed($photo->path) : Storage::disk('public')->url($photo->path),
            ])->filter(fn (array $photo): bool => $photo['url'] !== '')->values()->all(),
        ], range(1, HarProgram5s5r::weeks($month, $year)));
    }

    /**
     * Keep the listed photos of a week, delete the others (files too) and add
     * new uploads up to {@see HarProgram5s5r::MAX_EVIDENCE}.
     *
     * @param  array{unit_id: int, year: int, month: int, minggu: int}  $week
     * @param  list<int>  $keep
     * @param  list<UploadedFile>  $uploads
     */
    private function syncEvidence(array $week, array $keep, array $uploads, int $userId): void
    {
        /** @var Collection<int, HarProgram5s5rEvidence> $existing */
        $existing = HarProgram5s5rEvidence::query()->where($week)->orderBy('sort_order')->get();

        foreach ($existing->reject(fn (HarProgram5s5rEvidence $photo): bool => in_array($photo->id, $keep, true)) as $photo) {
            Storage::disk('public')->delete($photo->path);
            $photo->delete();
        }

        $count = $existing->filter(fn (HarProgram5s5rEvidence $photo): bool => in_array($photo->id, $keep, true))->count();
        foreach (array_slice($uploads, 0, max(0, HarProgram5s5r::MAX_EVIDENCE - $count)) as $file) {
            HarProgram5s5rEvidence::query()->create([
                ...$week,
                'path' => $file->store("har-5s5r/{$week['unit_id']}/{$week['year']}-{$week['month']}", 'public'),
                'sort_order' => $count++,
                'input_by' => $userId,
            ]);
        }
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    private function target(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', $request->integer('unit_id')) ?? $units->first();
        abort_unless($user->canAccessUnit($unit), 403);
        $unit->loadMissing('serviceUnit');

        $now = Carbon::now();

        return [
            $units,
            $unit,
            max(1, min(12, $request->integer('month') ?: (int) $now->month)),
            max(2000, min(2100, $request->integer('year') ?: (int) $now->year)),
        ];
    }

    private function authorizeView(User $user): void
    {
        abort_unless($user->hasPermissionTo(PermissionName::HarInputView) || $user->hasPermissionTo(PermissionName::HarLaporanView), 403);
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function embed(string $path): string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return '';
        }

        return 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode((string) $disk->get($path));
    }
}
