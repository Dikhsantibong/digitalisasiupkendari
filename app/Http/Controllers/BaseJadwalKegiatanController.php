<?php

namespace App\Http\Controllers;

use App\Enums\ActivityEvent;
use App\Models\Holiday;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\JadwalKegiatanGroups;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jadwal Kegiatan of a divisi (user, 2026-11-25): one monthly sheet of the
 * divisi's activities in the groups I. Harian, II. Mingguan, III. Bulanan and
 * IV. Non Rutin ({@see JadwalKegiatanGroups}); a date is ticked when the
 * activity was done (realisasi / kinerja against the target). Shared by K3
 * (Kegiatan Rutin K3L), Operasi and Pemeliharaan — a divisi controller only
 * declares its model (columns unit_id, year, month, grup, no_urut, kegiatan,
 * target, jadwal, keterangan, sort_order, input_by), defaults, permissions,
 * page and titles. The page is components/jadwal/kegiatan-jadwal-page.tsx.
 */
abstract class BaseJadwalKegiatanController extends Controller
{
    public function __construct(protected readonly ActivityLogger $activityLogger) {}

    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * Activities proposed for a new month, per group.
     *
     * @return array<string, list<string>>
     */
    abstract protected function defaults(): array;

    abstract protected function canView(User $user): bool;

    abstract protected function canWrite(User $user): bool;

    /** Inertia page component. */
    abstract protected function page(): string;

    /** Name of the sheet, e.g. "Jadwal Kegiatan Operasi" (logs, toasts, PDF). */
    abstract protected function label(): string;

    /** Last kop line of the PDF, e.g. "JADWAL KEGIATAN OPERASI". */
    abstract protected function kopTitle(): string;

    /** PDF view (receives unit, monthName, year, days, groups, logos, kopTitle). */
    protected function pdfTemplate(): string
    {
        return 'reports.jadwal-kegiatan-pdf';
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year] = $this->period($request);
        [$days, $targetWorkingDays] = $this->buildDays($year, $month, withMeta: true);

        $saved = $this->records($unit->id, $month, $year);
        $rows = $saved->isEmpty()
            ? $this->defaultRows($targetWorkingDays)
            : $saved->map(fn (Model $r): array => [
                'id' => $r->getKey(),
                'grup' => JadwalKegiatanGroups::normalize((string) $r->grup),
                'no_urut' => $r->no_urut,
                'kegiatan' => $r->kegiatan,
                'target' => (int) $r->target,
                'realisasi_count' => count($r->jadwal ?? []),
                'jadwal' => $r->jadwal ?? [],
                'keterangan' => $r->keterangan ?? '',
                'sort_order' => (int) $r->sort_order,
            ])->values()->all();

        $now = Carbon::now();

        return Inertia::render($this->page(), [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_id' => $unit->service_unit_id,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'days' => $days,
            'target_working_days' => $targetWorkingDays,
            'rows' => $rows,
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.id' => ['nullable', 'integer'],
            // A page opened before the groups (cached bundle) sends none: harian.
            'rows.*.grup' => ['nullable', 'string', Rule::in(JadwalKegiatanGroups::keys())],
            'rows.*.no_urut' => ['nullable', 'integer'],
            'rows.*.kegiatan' => ['required', 'string', 'max:500'],
            'rows.*.target' => ['required', 'integer', 'min:0'],
            'rows.*.jadwal' => ['nullable', 'array'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $model = $this->model();

        DB::transaction(function () use ($validated, $user, $unit, $month, $year, $model): void {
            $kept = [];

            foreach ($validated['rows'] ?? [] as $index => $row) {
                $jadwal = collect($row['jadwal'] ?? [])
                    ->map(fn ($d): int => (int) $d)
                    ->filter(fn (int $d): bool => $d >= 1 && $d <= 31)
                    ->unique()->sort()->values()->all();

                $attributes = $this->rowAttributes([
                    'grup' => JadwalKegiatanGroups::normalize((string) ($row['grup'] ?? 'harian')),
                    'no_urut' => $row['no_urut'] ?? ($index + 1),
                    'kegiatan' => $row['kegiatan'],
                    'target' => (int) ($row['target'] ?? 0),
                    'jadwal' => $jadwal,
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ]);

                $record = empty($row['id']) ? null : $model::query()->where('unit_id', $unit->id)->whereKey($row['id'])->first();
                if ($record !== null) {
                    $record->update($attributes);
                } else {
                    $record = $model::query()->create([...$attributes, 'unit_id' => $unit->id, 'year' => $year, 'month' => $month]);
                }
                $kept[] = $record->getKey();
            }

            $model::query()
                ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
                ->when($kept !== [], fn (Builder $q) => $q->whereKeyNot($kept))
                ->delete();
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan {$this->label()} {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        return back()->with('toast', ['type' => 'success', 'message' => "{$this->label()} berhasil disimpan."]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->with('serviceUnit')->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year] = $this->period($request);
        [$view, $data] = $this->pdfView($unit, $month, $year);

        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        $name = str_replace(' ', '_', $this->label().' '.$unit->name);

        return $pdf->download("{$name}_{$month}_{$year}.pdf");
    }

    /**
     * The PDF view & data of a month — also embedded in the divisi's laporan
     * (e.g. Laporan Operasi Pembangkit), like every other jadwal.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        [$days, $targetWorkingDays] = $this->buildDays($year, $month, withMeta: false);
        $records = $this->records($unit->id, $month, $year);

        $groups = [];
        foreach (JadwalKegiatanGroups::GROUPS as $grup => [$roman, $label]) {
            $items = $records->filter(fn (Model $r): bool => JadwalKegiatanGroups::normalize((string) $r->grup) === $grup)->values();
            $rows = $items->isEmpty() && $records->isEmpty()
                ? array_map(fn (string $k, int $i): array => $this->pdfRow($i + 1, $k, [], JadwalKegiatanGroups::defaultTarget($grup, $targetWorkingDays), ''), $this->defaults()[$grup] ?? [], array_keys($this->defaults()[$grup] ?? []))
                : $items->map(fn (Model $r, int $i): array => $this->pdfRow((int) ($r->no_urut ?: $i + 1), (string) $r->kegiatan, $r->jadwal ?? [], $r->target !== null ? (int) $r->target : JadwalKegiatanGroups::defaultTarget($grup, $targetWorkingDays), (string) ($r->keterangan ?? '')))->all();
            $groups[] = ['roman' => $roman, 'label' => $label, 'rows' => $rows];
        }

        $logo = fn (string $path, string $mime): ?string => file_exists(public_path($path))
            ? "data:{$mime};base64,".base64_encode((string) file_get_contents(public_path($path)))
            : null;

        return [$this->pdfTemplate(), [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => mb_strtoupper(Carbon::create($year, $month, 1)->locale('id')->isoFormat('MMMM')),
            'days' => $days,
            'groups' => $groups,
            'kopTitle' => $this->kopTitle(),
            'documentLabel' => $this->label(),
            'logoLeft' => $logo('logo/sidebar-logo.png', 'image/png'),
            'logoRight' => $logo('logo/mkp.jpg', 'image/jpeg'),
            'has_saved' => $records->isNotEmpty(),
            'rows' => array_merge(...array_column($groups, 'rows')),
        ]];
    }

    /**
     * Hook for a divisi whose table has extra columns (e.g. HAR's rencana_count).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function rowAttributes(array $attributes): array
    {
        return $attributes;
    }

    /**
     * @return Collection<int, Model>
     */
    protected function records(int $unitId, int $month, int $year): Collection
    {
        $model = $this->model();

        return $model::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy($model::query()->getModel()->getKeyName())
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function defaultRows(int $target): array
    {
        $rows = [];
        $sort = 0;
        foreach (JadwalKegiatanGroups::keys() as $grup) {
            foreach ($this->defaults()[$grup] ?? [] as $idx => $kegiatan) {
                $rows[] = [
                    'id' => null,
                    'grup' => $grup,
                    'no_urut' => $idx + 1,
                    'kegiatan' => $kegiatan,
                    'target' => JadwalKegiatanGroups::defaultTarget($grup, $target),
                    'realisasi_count' => 0,
                    'jadwal' => [],
                    'keterangan' => '',
                    'sort_order' => $sort++,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<int>  $jadwal
     * @return array<string, mixed>
     */
    private function pdfRow(int $no, string $kegiatan, array $jadwal, int $target, string $keterangan): array
    {
        $realisasi = count($jadwal);

        return [
            'no_urut' => $no,
            'kegiatan' => $kegiatan,
            'target' => $target,
            'realisasi_count' => $realisasi,
            'performance' => $target > 0 ? (int) round($realisasi / $target * 100) : 0,
            'jadwal' => $jadwal,
            'keterangan' => $keterangan,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = Carbon::now();

        return [max(1, min(12, (int) ($request->integer('month') ?: $now->month))), (int) ($request->integer('year') ?: $now->year)];
    }

    /**
     * Day cells of the month (weekend / holiday marked) and the working-day target.
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    protected function buildDays(int $year, int $month, bool $withMeta): array
    {
        $holidays = Holiday::query()->whereYear('date', $year)->whereMonth('date', $month)->get(['date', 'description']);

        $days = collect(range(1, (int) Carbon::create($year, $month, 1)->daysInMonth))->map(function (int $day) use ($year, $month, $holidays, $withMeta): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);
            $isWeekend = $date->isSaturday() || $date->isSunday();
            $isRed = $isWeekend || $holiday !== null;

            return $withMeta ? [
                'day' => $day,
                'dow' => $date->locale('id')->isoFormat('dd'),
                'is_weekend' => $isWeekend,
                'is_holiday' => $holiday !== null,
                'is_red' => $isRed,
                'holiday' => $holiday?->description,
            ] : ['day' => $day, 'is_red' => $isRed];
        })->all();

        return [$days, collect($days)->where('is_red', false)->count()];
    }
}
