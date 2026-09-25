<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\EmployeePresence;
use App\Services\ActivityLogger;
use App\Services\Operator\PresenceRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Absen masuk / absen pulang (Operator module). The phone sends its GPS
 * position; {@see PresenceRecorder} accepts it only within the unit's office
 * radius and records the account's scheduled shift. Guarded by
 * operator.presensi.
 */
class PresensiController extends Controller
{
    public function __construct(
        private readonly PresenceRecorder $recorder,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorPresensi), 403);

        $employee = $this->recorder->employeeOf($user);
        $unit = $employee?->unit;
        $shift = $employee !== null ? $this->recorder->currentShift($employee) : null;
        $today = $shift !== null ? $this->recorder->presenceOn($employee, $shift['work_date']) : null;
        $open = $employee !== null ? $this->recorder->openPresence($employee) : null;

        return Inertia::render('operator/presensi', [
            'employee' => $employee === null ? null : [
                'name' => $employee->name,
                'position' => $employee->position,
                'regu' => $employee->regu,
                'is_shift_leader' => $employee->is_shift_leader,
            ],
            'shift' => $shift === null ? null : [
                'work_date' => $shift['work_date'],
                'code' => $shift['code']?->code,
                'label' => $shift['code']?->label,
                'jam_mulai' => $shift['code']?->jam_mulai,
                'jam_selesai' => $shift['code']?->jam_selesai,
                'is_working' => $this->recorder->isWorkingShift($shift['code']),
            ],
            'office' => $unit === null ? null : [
                'unit' => $unit->name,
                'latitude' => $unit->latitude === null ? null : (float) $unit->latitude,
                'longitude' => $unit->longitude === null ? null : (float) $unit->longitude,
                'radius_m' => $unit->attendance_radius_m,
            ],
            'today' => $today === null ? null : $this->present($today),
            'open' => $open === null ? null : $this->present($open),
            'timezone' => PresenceRecorder::TIMEZONE,
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo(PermissionName::OperatorPresensi), 403);

        [$latitude, $longitude, $accuracy] = $this->position($request);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;
        $presence = $this->recorder->checkIn($request->user(), $latitude, $longitude, $accuracy, $note);

        $this->activityLogger->log(ActivityEvent::Created, 'Absen masuk', unit: $presence->unit_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Absen masuk tercatat ({$presence->check_in_distance_m} m dari kantor)."]);

        return back();
    }

    public function checkOut(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo(PermissionName::OperatorPresensi), 403);

        [$latitude, $longitude, $accuracy] = $this->position($request);
        $presence = $this->recorder->checkOut($request->user(), $latitude, $longitude, $accuracy);

        $this->activityLogger->log(ActivityEvent::Updated, 'Absen pulang', unit: $presence->unit_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Absen pulang tercatat ({$presence->check_out_distance_m} m dari kantor)."]);

        return back();
    }

    /**
     * @return array{0: float, 1: float, 2: int|null}
     */
    private function position(Request $request): array
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        return [
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            isset($validated['accuracy']) ? (int) round((float) $validated['accuracy']) : null,
        ];
    }

    /**
     * @return array{work_date: string, shift_code: string|null, late_minutes: int|null, check_in_note: string|null, check_in_at: string, check_in_distance_m: int, check_out_at: string|null, check_out_distance_m: int|null}
     */
    private function present(EmployeePresence $presence): array
    {
        return [
            'work_date' => $presence->work_date->toDateString(),
            'shift_code' => $presence->shift_code,
            'late_minutes' => $presence->late_minutes,
            'check_in_note' => $presence->check_in_note,
            'check_in_at' => $presence->check_in_at->toIso8601String(),
            'check_in_distance_m' => $presence->check_in_distance_m,
            'check_out_at' => $presence->check_out_at?->toIso8601String(),
            'check_out_distance_m' => $presence->check_out_distance_m,
        ];
    }
}
