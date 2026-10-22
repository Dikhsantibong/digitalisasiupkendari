<?php

namespace App\Http\Controllers\Operator;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use App\Services\Operator\OperatorResume;
use App\Services\Operator\PresenceRecorder;
use App\Support\Indonesian;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resume Statistik (modul OPERATOR) — realisasi shift harian per operator:
 * kehadiran against the Jadwal Shift, tukar shift and the work saved, plus the
 * day-by-day detail of one employee ({@see OperatorResume}).
 *
 * The approvers (operator.absensi.write) and the read-only supervisors
 * (operator.absensi.view without presensi, e.g. TL Operasi, Manager UL) see
 * every employee of the unit; an operator sees only their own resume.
 */
class ResumeController extends Controller
{
    public function __construct(
        private readonly OperatorResume $resume,
        private readonly PresenceRecorder $presence,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorPresensi) || $user->hasPermissionTo(PermissionName::OperatorAbsensiView), 403);

        $me = $this->presence->employeeOf($user);
        $seesAll = $this->seesAll($user);
        abort_if(! $seesAll && $me === null, 403, 'Akun Anda belum terhubung ke data pegawai.');

        $units = $seesAll
            ? Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name'])
            : Unit::query()->whereKey($me->unit_id)->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id'))
            ?? ($me !== null ? $units->firstWhere('id', $me->unit_id) : null)
            ?? $units->first();
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now(PresenceRecorder::TIMEZONE);
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = max(2000, min(2100, (int) ($request->integer('year') ?: $now->year)));

        $employees = $this->resume->employees($unit, $month, $year, $seesAll ? null : $me);

        // The day-by-day detail: the chosen employee, the viewer's own row, or the first one.
        $selectedId = $seesAll ? (int) $request->integer('employee_id') : $me->id;
        $selected = collect($employees)->firstWhere('id', $selectedId)
            ?? collect($employees)->firstWhere('id', $me?->id)
            ?? ($employees[0] ?? null);
        $detail = $selected !== null ? Employee::query()->find($selected['id']) : null;

        return Inertia::render('operator/resume/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'employee_id' => $detail?->id],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 1, $now->year + 1),
                'pekerjaan' => OperatorResume::PEKERJAAN,
                'absen' => OperatorResume::ABSENCE_CODES,
            ],
            'employees' => $employees,
            'selected' => $selected,
            'days' => $detail !== null ? $this->resume->days($unit, $detail, $month, $year) : [],
            'sees_all' => $seesAll,
            'me_id' => $me?->id,
        ]);
    }

    private function seesAll(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::OperatorAbsensiWrite)
            || ($user->hasPermissionTo(PermissionName::OperatorAbsensiView) && ! $user->hasPermissionTo(PermissionName::OperatorPresensi));
    }
}
