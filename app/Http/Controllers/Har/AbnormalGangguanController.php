<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Operasi\KondisiAbnormalController;
use App\Models\HarDocumentRecord;
use App\Models\KondisiAbnormal;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notifications\EventNotifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan Kondisi Abnormal & Gangguan Pembangkit (Input Pemeliharaan / HAR).
 *
 * Menggunakan tabel dan format yang sama persis dengan Input Operasi → Kondisi
 * Abnormal & Gangguan ({@see KondisiAbnormal}, {@see KondisiAbnormalController}),
 * sehingga setiap data abnormal/gangguan yang diinput oleh Operasi otomatis
 * masuk dan tampil di Pemeliharaan (dan sebaliknya), baik pada tampilan desktop,
 * mobile, PDF, Excel, maupun Laporan Project.
 */
class AbnormalGangguanController extends Controller
{
    use AuthorizesFieldInput;

    /** Short-term follow-up columns => label. */
    public const TINDAK_PENDEK = KondisiAbnormalController::TINDAK_PENDEK;

    /** Jenis kejadian => label. */
    public const JENIS = KondisiAbnormalController::JENIS;

    /** Free-text follow-up & impact columns. */
    private const TEXT_FIELDS = ['parameter_terkait', 'tl_pendek_keterangan', 'tl_panjang', 'dampak_sistem', 'dampak_pembangkit'];

    /** @var array<string, string> */
    private const EVIDEN_MESSAGES = [
        'eviden_file.image' => 'Eviden harus berupa foto.',
        'eviden_file.max' => 'Ukuran foto eviden paling besar 10 MB.',
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        [$units, $unit, $month, $year] = $this->target($request);
        $records = $this->records($unit, $month, $year);

        return Inertia::render('har/input/abnormal-gangguan/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'entries' => $records->values()->map(fn (KondisiAbnormal $record, int $index): array => [
                ...$this->present($record, $index + 1),
                'input_by' => $record->inputUser?->name,
                'can_edit' => $this->canEdit($user, $record),
            ])->all(),
            'summary' => $this->summary($records),
            'rekap_harian' => $this->daily($records),
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'tindak_pendek' => self::TINDAK_PENDEK,
                'jenis' => self::JENIS,
                'machines' => Machine::query()->where('unit_id', $unit->id)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Machine $m): array => ['id' => $m->id, 'name' => $m->name])->all(),
            ],
            'can_write' => $this->canWrite($user),
            'today' => Carbon::now('Asia/Makassar')->toDateString(),
        ]);
    }

    public function store(Request $request, EventNotifier $events): RedirectResponse
    {
        if ($request->has('rows') && is_array($request->input('rows'))) {
            return $this->sync($request, $events);
        }

        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $input = $this->validated($request);
        $values = $this->normalize($input);
        $date = Carbon::parse($values['tanggal']);

        $record = KondisiAbnormal::query()->create([
            'unit_id' => $unit->id,
            'year' => $date->year,
            'month' => $date->month,
            'no_urut' => $this->nextNumber($unit->id, $date->year, $date->month),
            ...$values,
            'eviden' => $this->evidenAfterSave($input, null, $unit->id),
            'input_by' => $user->id,
        ]);

        $this->invalidateHarReportCache($unit->id, $date->month, $date->year);
        $events->kondisiAbnormal($unit, [$record], $user);
        $this->activityLogger->log(ActivityEvent::Created, "Melaporkan kondisi {$this->jenisOf($record)} (Pemeliharaan) {$unit->name} tanggal {$date->format('d/m/Y')}", $record, unit: $unit->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan '.$this->jenisOf($record).' tersimpan.']);

        return redirect()->route('har.input.abnormal-gangguan.index', ['unit_id' => $unit->id, 'month' => $date->month, 'year' => $date->year]);
    }

    public function update(Request $request, KondisiAbnormal $kondisiAbnormal, EventNotifier $events): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canEdit($user, $kondisiAbnormal), 403);

        $input = $this->validated($request);
        $values = $this->normalize($input);
        $date = Carbon::parse($values['tanggal']);
        $moved = $date->year !== $kondisiAbnormal->year || $date->month !== $kondisiAbnormal->month;
        $previousEviden = $kondisiAbnormal->eviden;

        $kondisiAbnormal->fill([
            ...$values,
            'eviden' => $this->evidenAfterSave($input, $previousEviden, $kondisiAbnormal->unit_id),
            'year' => $date->year,
            'month' => $date->month,
        ]);
        if ($moved) {
            $kondisiAbnormal->no_urut = $this->nextNumber($kondisiAbnormal->unit_id, $date->year, $date->month);
        }
        $kondisiAbnormal->save();
        $this->deleteEviden($previousEviden !== $kondisiAbnormal->eviden ? [$previousEviden] : []);

        $this->invalidateHarReportCache($kondisiAbnormal->unit_id, $date->month, $date->year);
        if ($kondisiAbnormal->wasChanged(['is_abnormal', 'is_gangguan'])) {
            $events->kondisiAbnormal($kondisiAbnormal->unit, [$kondisiAbnormal], $user);
        }
        $this->activityLogger->log(ActivityEvent::Updated, "Mengubah laporan kondisi {$this->jenisOf($kondisiAbnormal)} #{$kondisiAbnormal->id}", $kondisiAbnormal, unit: $kondisiAbnormal->unit_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan diperbarui.']);

        return redirect()->route('har.input.abnormal-gangguan.index', ['unit_id' => $kondisiAbnormal->unit_id, 'month' => $date->month, 'year' => $date->year]);
    }

    /**
     * Save the desktop sheet at once (new rows, edited rows, and deleted rows).
     */
    public function sync(Request $request, EventNotifier $events): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['present', 'array', 'max:500'],
            'rows.*.id' => ['nullable', 'integer'],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => ['integer'],
        ]);
        $month = $request->integer('month');
        $year = $request->integer('year');
        $first = Carbon::create($year, $month, 1)->toDateString();
        $last = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $rawRows = (array) ($request->all()['rows'] ?? []);
        ksort($rawRows);

        // Normalize legacy shorthand row keys if posted to store/sync.
        $rows = [];
        foreach ($rawRows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! isset($row['uraian_kondisi']) && isset($row['uraian'])) {
                $jenisHar = trim((string) ($row['jenis'] ?? ''));
                $uraian = trim((string) $row['uraian']);
                $row['uraian_kondisi'] = ($jenisHar !== '' && ! in_array(strtolower($jenisHar), ['abnormal', 'gangguan'], true))
                    ? "{$uraian} ({$jenisHar})"
                    : $uraian;
            }
            if (! isset($row['jenis']) || ! in_array($row['jenis'], ['abnormal', 'gangguan'], true)) {
                $row['jenis'] = (! empty($row['gangguan']) && empty($row['abnormal'])) ? 'gangguan' : 'abnormal';
            }
            if (! isset($row['durasi'])) {
                $row['durasi'] = $row['jenis'] === 'gangguan'
                    ? ($row['durasi_gangguan'] ?? 0)
                    : ($row['durasi_abnormal'] ?? 0);
            }
            if (empty($row['tanggal'])) {
                $row['tanggal'] = $first;
            }
            if (! empty($row['id']) || trim((string) ($row['uraian_kondisi'] ?? '')) !== '') {
                $rows[$index] = $row;
            }
        }

        $validated = Validator::make(['rows' => $rows], [
            ...collect($this->rowRules($unit->id))->mapWithKeys(fn (array $rules, string $key): array => ["rows.*.{$key}" => $rules])->all(),
            'rows.*.tanggal' => ['required', 'date_format:Y-m-d', "after_or_equal:{$first}", "before_or_equal:{$last}"],
        ], [
            'rows.*.tanggal.after_or_equal' => 'Tanggal baris harus di bulan yang ditampilkan.',
            'rows.*.tanggal.before_or_equal' => 'Tanggal baris harus di bulan yang ditampilkan.',
            'rows.*.uraian_kondisi.required' => 'Indikasi / uraian kondisi wajib diisi.',
            ...collect(self::EVIDEN_MESSAGES)->mapWithKeys(fn (string $message, string $key): array => ["rows.*.{$key}" => $message])->all(),
        ])->validate();

        $existing = KondisiAbnormal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->get()->keyBy('id');
        foreach (array_keys($rows) as $index) {
            $id = (int) ($rows[$index]['id'] ?? 0);
            if ($id === 0) {
                continue;
            }
            $record = $existing->get($id);
            if ($record === null) {
                throw ValidationException::withMessages(["rows.{$index}.id" => 'Laporan ini tidak ada di unit & bulan tersebut. Muat ulang halaman.']);
            }
            abort_unless($this->canEdit($user, $record), 403);
        }
        $deletedIds = collect($request->input('deleted_ids', []))->map(fn ($id): int => (int) $id)->unique();
        foreach ($deletedIds as $id) {
            $record = $existing->get($id);
            abort_if($record !== null && ! $this->canEdit($user, $record), 403);
        }

        $announce = [];
        $counts = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        $removedEviden = [];

        DB::transaction(function () use ($rows, $validated, $existing, $deletedIds, $unit, $year, $month, $user, &$announce, &$counts, &$removedEviden): void {
            $number = $this->nextNumber($unit->id, $year, $month);

            foreach (array_keys($rows) as $index) {
                $values = $this->normalize($validated['rows'][$index]);
                $id = (int) ($rows[$index]['id'] ?? 0);

                if ($id !== 0) {
                    $record = $existing->get($id);
                    $previousEviden = $record->eviden;
                    $record->fill([...$values, 'eviden' => $this->evidenAfterSave($validated['rows'][$index], $previousEviden, $unit->id)]);
                    if ($record->isDirty('eviden')) {
                        $removedEviden[] = $previousEviden;
                    }
                    if ($record->isDirty()) {
                        $record->save();
                        $counts['updated']++;
                        if ($record->wasChanged(['is_abnormal', 'is_gangguan'])) {
                            $announce[] = $record;
                        }
                    }

                    continue;
                }

                $announce[] = KondisiAbnormal::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => $number++,
                    ...$values,
                    'eviden' => $this->evidenAfterSave($validated['rows'][$index], null, $unit->id),
                    'input_by' => $user->id,
                ]);
                $counts['created']++;
            }

            foreach ($deletedIds as $id) {
                if ($existing->has($id) && ! collect($rows)->contains(fn (array $row): bool => (int) ($row['id'] ?? 0) === $id)) {
                    $removedEviden[] = $existing->get($id)->eviden;
                    $existing->get($id)->delete();
                    $counts['deleted']++;
                }
            }
        });
        $this->deleteEviden($removedEviden);
        $this->invalidateHarReportCache($unit->id, $month, $year);

        if ($announce !== []) {
            $events->kondisiAbnormal($unit, $announce, $user);
        }
        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan lembar kondisi abnormal & gangguan (Pemeliharaan) {$unit->name} {$month}/{$year}: {$counts['created']} baru, {$counts['updated']} diubah, {$counts['deleted']} dihapus", $unit, unit: $unit->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Tersimpan: {$counts['created']} laporan baru, {$counts['updated']} diubah, {$counts['deleted']} dihapus."]);

        return redirect()->route('har.input.abnormal-gangguan.index', ['unit_id' => $unit->id, 'month' => $month, 'year' => $year]);
    }

    public function destroy(Request $request, KondisiAbnormal $kondisiAbnormal): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canEdit($user, $kondisiAbnormal), 403);

        $period = ['unit_id' => $kondisiAbnormal->unit_id, 'month' => $kondisiAbnormal->month, 'year' => $kondisiAbnormal->year];
        $kondisiAbnormal->delete();
        $this->deleteEviden([$kondisiAbnormal->eviden]);
        $this->invalidateHarReportCache($period['unit_id'], $period['month'], $period['year']);

        $this->activityLogger->log(ActivityEvent::Deleted, "Menghapus laporan kondisi abnormal/gangguan #{$kondisiAbnormal->id}", unit: $period['unit_id']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan dihapus.']);

        return redirect()->route('har.input.abnormal-gangguan.index', $period);
    }

    public function pdf(Request $request): HttpResponse
    {
        abort_unless($this->canView($request->user()), 403);

        [, $unit, $month, $year] = $this->target($request);

        [$view, $data] = $this->pdfView($unit, $month, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Laporan_Kondisi_Abnormal_Pemeliharaan_{$safeUnitName}_{$month}_{$year}.pdf");
    }

    /**
     * Reuse the exact same PDF view & data structure as Operasi KondisiAbnormalController.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        return app(KondisiAbnormalController::class)->pdfView($unit, $month, $year);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'unit_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            ...$this->rowRules($request->integer('unit_id')),
        ], self::EVIDEN_MESSAGES);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rowRules(int $unitId): array
    {
        return [
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i'],
            'machine_id' => ['nullable', 'integer', Rule::exists('machines', 'id')->where('unit_id', $unitId)],
            'peralatan' => ['nullable', 'string', 'max:255'],
            'jenis' => ['required', 'in:abnormal,gangguan'],
            'durasi' => ['nullable', 'numeric', 'min:0', 'max:744'],
            'uraian_kondisi' => ['required', 'string', 'max:2000'],
            'parameter_terkait' => ['nullable', 'string', 'max:2000'],
            'kronologi' => ['nullable', 'string', 'max:5000'],
            'tl_pendek_sr' => ['nullable', 'boolean'],
            'tl_pendek_flm' => ['nullable', 'boolean'],
            'tl_pendek_lainnya' => ['nullable', 'boolean'],
            'tl_pendek_keterangan' => ['nullable', 'string', 'max:2000'],
            'tl_panjang' => ['nullable', 'string', 'max:2000'],
            'dampak_sistem' => ['nullable', 'string', 'max:2000'],
            'dampak_pembangkit' => ['nullable', 'string', 'max:2000'],
            'eviden' => ['nullable', 'string', 'max:255'],
            'eviden_file' => ['nullable', 'image', 'max:10240'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalize(array $validated): array
    {
        $abnormal = $validated['jenis'] === 'abnormal';
        $mulai = $abnormal ? null : ($validated['jam_mulai'] ?? null);
        $selesai = $abnormal ? null : ($validated['jam_selesai'] ?? null);
        $durasi = $mulai !== null && $selesai !== null
            ? KondisiAbnormalController::hoursBetween($mulai, $selesai)
            : (float) ($validated['durasi'] ?? 0);

        return [
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'machine_id' => $abnormal ? null : ($validated['machine_id'] ?? null),
            'peralatan' => $abnormal ? null : (trim((string) ($validated['peralatan'] ?? '')) ?: null),
            'kronologi' => $abnormal ? null : (trim((string) ($validated['kronologi'] ?? '')) ?: null),
            'tanggal' => $validated['tanggal'],
            'uraian_kondisi' => trim((string) $validated['uraian_kondisi']),
            'is_abnormal' => $abnormal ? 1 : 0,
            'durasi_abnormal' => $abnormal ? $durasi : 0,
            'is_gangguan' => $abnormal ? 0 : 1,
            'durasi_gangguan' => $abnormal ? 0 : $durasi,
            ...collect(self::TINDAK_PENDEK)->keys()->mapWithKeys(fn (string $key): array => [$key => (bool) ($validated[$key] ?? false)])->all(),
            ...collect(self::TEXT_FIELDS)->mapWithKeys(fn (string $key): array => [$key => trim((string) ($validated[$key] ?? '')) ?: null])->all(),
        ];
    }

    /**
     * @return Collection<int, KondisiAbnormal>
     */
    private function records(Unit $unit, int $month, int $year): Collection
    {
        return KondisiAbnormal::query()
            ->with(['inputUser:id,name', 'machine:id,name'])
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(KondisiAbnormal $record, int $number): array
    {
        $row = [
            'id' => $record->id,
            'no' => $number,
            'tanggal' => $record->tanggal?->format('Y-m-d') ?? '',
            'jenis' => (int) $record->is_gangguan === 1 && (int) $record->is_abnormal !== 1 ? 'gangguan' : 'abnormal',
            'uraian_kondisi' => (string) ($record->uraian_kondisi ?? ''),
            'is_abnormal' => (int) $record->is_abnormal,
            'durasi_abnormal' => (float) $record->durasi_abnormal,
            'is_gangguan' => (int) $record->is_gangguan,
            'durasi_gangguan' => (float) $record->durasi_gangguan,
        ];
        $row['durasi'] = $row['durasi_abnormal'] + $row['durasi_gangguan'];
        $row['jam_mulai'] = (string) ($record->jam_mulai ?? '');
        $row['jam_selesai'] = (string) ($record->jam_selesai ?? '');
        $row['machine_id'] = $record->machine_id;
        $row['mesin'] = (string) ($record->machine?->name ?? '');
        $row['peralatan'] = (string) ($record->peralatan ?? '');
        $row['kronologi'] = (string) ($record->kronologi ?? '');
        $row['eviden'] = $record->eviden;
        $row['eviden_url'] = $this->evidenUrl($record->eviden);
        foreach (array_keys(self::TINDAK_PENDEK) as $key) {
            $row[$key] = $record->{$key} ? 1 : 0;
        }
        foreach (self::TEXT_FIELDS as $key) {
            $row[$key] = (string) ($record->{$key} ?? '');
        }

        return $row;
    }

    /**
     * @param  Collection<int, KondisiAbnormal>  $records
     * @return array{total_abnormal_count: int, total_abnormal_durasi: float, total_gangguan_count: int, total_gangguan_durasi: float, tindak_pendek: array<string, int>}
     */
    private function summary(Collection $records): array
    {
        return [
            'total_abnormal_count' => $records->filter(fn (KondisiAbnormal $r): bool => (int) $r->is_abnormal === 1)->count(),
            'total_abnormal_durasi' => (float) $records->sum(fn (KondisiAbnormal $r): float => (float) $r->durasi_abnormal),
            'total_gangguan_count' => $records->filter(fn (KondisiAbnormal $r): bool => (int) $r->is_gangguan === 1)->count(),
            'total_gangguan_durasi' => (float) $records->sum(fn (KondisiAbnormal $r): float => (float) $r->durasi_gangguan),
            'tindak_pendek' => collect(self::TINDAK_PENDEK)->keys()
                ->mapWithKeys(fn (string $key): array => [$key => $records->filter(fn (KondisiAbnormal $r): bool => (bool) $r->{$key})->count()])->all(),
        ];
    }

    /**
     * @param  Collection<int, KondisiAbnormal>  $records
     * @return list<array{tanggal: string, abnormal: int, durasi_abnormal: float, gangguan: int, durasi_gangguan: float}>
     */
    private function daily(Collection $records): array
    {
        return $records->filter(fn (KondisiAbnormal $r): bool => $r->tanggal !== null)
            ->groupBy(fn (KondisiAbnormal $r): string => $r->tanggal->format('Y-m-d'))
            ->map(fn (Collection $day, string $date): array => [
                'tanggal' => $date,
                'abnormal' => $day->filter(fn (KondisiAbnormal $r): bool => (int) $r->is_abnormal === 1)->count(),
                'durasi_abnormal' => (float) $day->sum(fn (KondisiAbnormal $r): float => (float) $r->durasi_abnormal),
                'gangguan' => $day->filter(fn (KondisiAbnormal $r): bool => (int) $r->is_gangguan === 1)->count(),
                'durasi_gangguan' => (float) $day->sum(fn (KondisiAbnormal $r): float => (float) $r->durasi_gangguan),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function evidenAfterSave(array $input, ?string $current, int $unitId): ?string
    {
        $upload = $input['eviden_file'] ?? null;
        if ($upload instanceof UploadedFile) {
            return $upload->store("operasi-abnormal/{$unitId}", 'public');
        }

        if (! array_key_exists('eviden', $input)) {
            return $current;
        }

        return $current !== null && $input['eviden'] === $current ? $current : null;
    }

    /**
     * @param  list<string|null>  $paths
     */
    private function deleteEviden(array $paths): void
    {
        $paths = array_values(array_filter($paths));
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    private function evidenUrl(?string $path): ?string
    {
        $disk = Storage::disk('public');
        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        return $disk->url($path);
    }

    private function nextNumber(int $unitId, int $year, int $month): int
    {
        return (int) KondisiAbnormal::query()->where('unit_id', $unitId)->where('year', $year)->where('month', $month)->max('no_urut') + 1;
    }

    private function jenisOf(KondisiAbnormal $record): string
    {
        return (int) $record->is_gangguan === 1 ? 'gangguan' : 'abnormal';
    }

    private function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::HarInputView, PermissionName::HarLapanganAbnormalGangguan)
            || $user->hasPermissionTo(PermissionName::HarLaporanView);
    }

    private function canWrite(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganAbnormalGangguan) && ! $user->isReadOnly();
    }

    private function canEdit(User $user, KondisiAbnormal $record): bool
    {
        return $this->canWrite($user)
            && $user->canAccessUnit($record->unit_id);
    }

    private function invalidateHarReportCache(int $unitId, int $month, int $year): void
    {
        HarDocumentRecord::query()
            ->where('unit_id', $unitId)
            ->where('month', $month)
            ->where('year', $year)
            ->delete();
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    private function target(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        return [$units, $unit, $month, $year];
    }
}
