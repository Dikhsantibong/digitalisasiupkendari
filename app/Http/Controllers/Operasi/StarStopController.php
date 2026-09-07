<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operasi\StarStopStoreRequest;
use App\Models\EngineStatusLog;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\UnitStatusCode;
use App\Services\ActivityLogger;
use App\Services\Operasi\OperasiCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Star-Stop log (modul OPERASI, menu Input): the single source of machine
 * hours. Users record start/stop entries per machine; operating, maintenance,
 * disturbance and standby hours are derived by {@see OperasiCalculator}.
 */
class StarStopController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly OperasiCalculator $calculator,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();

        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $engine = $machines->firstWhere('id', (int) $request->integer('engine_id')) ?? $machines->first();

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        $logs = $engine === null ? collect() : EngineStatusLog::query()
            ->where('engine_id', $engine->id)
            ->whereYear('report_date', $year)
            ->whereMonth('report_date', $month)
            ->with('statusCode:id,code,label,category')
            ->orderBy('start_datetime')
            ->get();

        return Inertia::render('operasi/input/star-stop', [
            'filters' => [
                'unit_id' => $unit->id,
                'engine_id' => $engine?->id,
                'month' => $month,
                'year' => $year,
            ],
            'logs' => $logs->map(fn (EngineStatusLog $log): array => [
                'id' => $log->id,
                'report_date' => $log->report_date->toDateString(),
                'status_code' => $log->statusCode?->code,
                'status_label' => $log->statusCode?->label,
                'category' => $log->statusCode?->category?->value,
                'operator_name' => $log->operator_name,
                'dispatcher_name' => $log->dispatcher_name,
                'start_datetime' => $log->start_datetime?->format('Y-m-d H:i'),
                'stop_datetime' => $log->stop_datetime?->format('Y-m-d H:i'),
                'duration_minutes' => $log->duration_minutes,
                'keterangan' => $log->keterangan,
            ])->all(),
            'hours' => $engine === null ? null : $this->calculator->hoursSummary($engine, $month, $year),
            'engine' => $engine === null ? null : ['id' => $engine->id, 'name' => $engine->name],
            'options' => [
                'units' => $units->all(),
                'machines' => $machines->all(),
                'status_codes' => UnitStatusCode::query()
                    ->forUnit($unit->id)
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get(['id', 'code', 'label', 'category'])
                    ->all(),
                'years' => range($year - 2, $year + 1),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiInputWrite),
        ]);
    }

    public function store(StarStopStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $engine = Machine::query()
            ->where('unit_id', $unit->id)
            ->findOrFail($request->integer('engine_id'));

        $statusCode = UnitStatusCode::query()
            ->forUnit($unit->id)
            ->findOrFail($request->integer('status_code_id'));

        $start = $request->date('start_datetime');
        $stop = $request->date('stop_datetime');

        EngineStatusLog::query()->create([
            'unit_id' => $unit->id,
            'engine_id' => $engine->id,
            'report_date' => $request->date('report_date')->toDateString(),
            'status_code_id' => $statusCode->id,
            'operator_name' => $request->string('operator_name')->value() ?: null,
            'dispatcher_name' => $request->string('dispatcher_name')->value() ?: null,
            'start_datetime' => $start,
            'stop_datetime' => $stop,
            'duration_minutes' => (int) round($start->diffInMinutes($stop)),
            'keterangan' => $request->string('keterangan')->value() ?: null,
            'input_by' => $user->id,
        ]);

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menambah entri Star-Stop {$engine->name} ({$statusCode->code})",
            $engine,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entri Star-Stop ditambahkan.']);

        return back();
    }

    public function destroy(Request $request, EngineStatusLog $engineStatusLog): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);
        abort_unless($user->canAccessUnit($engineStatusLog->unit_id), 403);

        $engineStatusLog->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            'Menghapus entri Star-Stop',
            unit: $engineStatusLog->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entri Star-Stop dihapus.']);

        return back();
    }
}
