<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\OperatorStartStop;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\Indonesian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Start Stop Engine (modul Operator): the runs of each machine of a month —
 * start, CBG on, CBG off and stop per tanggal, several a day — filled like a
 * spreadsheet and saved at once. Stops caused by a gangguan are the Jumlah
 * Kali Gangguan of Pengusahaan Operasi.
 *
 * Uses the logsheet permissions: operators & Project Leader write, TL /
 * Manager read.
 */
class StartStopController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');
        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        [$month, $year] = $this->period($request);

        $rows = OperatorStartStop::query()
            ->with('inputUser:id,name')
            ->where('unit_id', $unit->id)
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->orderBy('tanggal')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->map(fn (OperatorStartStop $row): array => [
                'id' => $row->id,
                'tanggal' => $row->tanggal->format('Y-m-d'),
                'engine_id' => $row->engine_id,
                'start_time' => (string) ($row->start_time ?? ''),
                'cbg_on' => (string) ($row->cbg_on ?? ''),
                'cbg_off' => (string) ($row->cbg_off ?? ''),
                'stop_time' => (string) ($row->stop_time ?? ''),
                'penyebab_stop' => $row->penyebab_stop,
                'keterangan' => (string) ($row->keterangan ?? ''),
                'input_by' => $row->inputUser?->name,
            ])->all();

        return Inertia::render('operator/start-stop/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
            'machines' => Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'rows' => $rows,
            'penyebab' => OperatorStartStop::PENYEBAB,
            'today' => Carbon::now('Asia/Makassar')->toDateString(),
            'can_write' => $user->hasPermissionTo(PermissionName::OperatorLogsheetWrite),
        ]);
    }

    /**
     * The month's sheet saved at once: rows with an id are updated, rows
     * without one are new runs (a row with no mesin and no time is skipped),
     * `deleted_ids` are removed.
     */
    public function sync(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'rows' => ['present', 'array', 'max:1000'],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => ['integer'],
        ]);
        $month = $request->integer('month');
        $year = $request->integer('year');
        $first = Carbon::create($year, $month, 1)->toDateString();
        $last = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        $times = ['start_time', 'cbg_on', 'cbg_off', 'stop_time'];

        $rows = (array) $request->input('rows', []);
        ksort($rows);
        $rows = array_filter($rows, fn ($row): bool => is_array($row) && (! empty($row['id']) || ! empty($row['engine_id'])
            || collect($times)->contains(fn (string $key): bool => trim((string) ($row[$key] ?? '')) !== '')));

        $validated = Validator::make(['rows' => $rows], [
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.tanggal' => ['required', 'date_format:Y-m-d', "after_or_equal:{$first}", "before_or_equal:{$last}"],
            'rows.*.engine_id' => ['required', 'integer', Rule::exists('machines', 'id')->where('unit_id', $unit->id)],
            ...collect($times)->mapWithKeys(fn (string $key): array => ["rows.*.{$key}" => ['nullable', 'date_format:H:i']])->all(),
            'rows.*.penyebab_stop' => ['required', Rule::in(array_keys(OperatorStartStop::PENYEBAB))],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'rows.*.tanggal.after_or_equal' => 'Tanggal harus di bulan yang ditampilkan.',
            'rows.*.tanggal.before_or_equal' => 'Tanggal harus di bulan yang ditampilkan.',
            'rows.*.engine_id.required' => 'Pilih mesinnya.',
            'rows.*.*.date_format' => 'Format jam HH:MM.',
        ])->validate();

        $existing = OperatorStartStop::query()->where('unit_id', $unit->id)
            ->whereYear('tanggal', $year)->whereMonth('tanggal', $month)->get()->keyBy('id');

        foreach (array_keys($rows) as $index) {
            $id = (int) ($rows[$index]['id'] ?? 0);
            if ($id !== 0 && ! $existing->has($id)) {
                throw ValidationException::withMessages(["rows.{$index}.id" => 'Data ini tidak ada di unit & bulan tersebut. Muat ulang halaman.']);
            }
        }

        $counts = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        DB::transaction(function () use ($rows, $validated, $existing, $unit, $user, $times, $request, &$counts): void {
            $kept = [];
            foreach (array_keys($rows) as $index) {
                $row = $validated['rows'][$index];
                $values = [
                    'tanggal' => $row['tanggal'],
                    'engine_id' => (int) $row['engine_id'],
                    ...collect($times)->mapWithKeys(fn (string $key): array => [$key => ($row[$key] ?? null) ?: null])->all(),
                    'penyebab_stop' => $row['penyebab_stop'],
                    'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
                ];
                $id = (int) ($rows[$index]['id'] ?? 0);

                if ($id !== 0) {
                    $record = $existing->get($id);
                    $record->fill($values);
                    if ($record->isDirty()) {
                        $record->save();
                        $counts['updated']++;
                    }
                    $kept[] = $id;

                    continue;
                }

                OperatorStartStop::query()->create([...$values, 'unit_id' => $unit->id, 'input_by' => $user->id]);
                $counts['created']++;
            }

            foreach (collect($request->input('deleted_ids', []))->map(fn ($id): int => (int) $id)->unique() as $id) {
                if ($existing->has($id) && ! in_array($id, $kept, true)) {
                    $existing->get($id)->delete();
                    $counts['deleted']++;
                }
            }
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Start Stop Engine {$unit->name} {$month}/{$year}: {$counts['created']} baru, {$counts['updated']} diubah, {$counts['deleted']} dihapus", $unit, unit: $unit->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Start Stop Engine tersimpan ({$counts['created']} baru, {$counts['updated']} diubah, {$counts['deleted']} dihapus)."]);

        return redirect()->route('operator.start-stop.index', ['unit_id' => $unit->id, 'month' => $month, 'year' => $year]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = Carbon::now('Asia/Makassar');

        return [
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            max(2020, min(2100, (int) ($request->integer('year') ?: $now->year))),
        ];
    }
}
