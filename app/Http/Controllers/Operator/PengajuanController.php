<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceRequestType;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notifications\EventNotifier;
use App\Services\Operator\AttendanceRequestService;
use App\Services\Operator\PresenceRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pengajuan Absensi (modul OPERATOR): an employee on the Jadwal Shift asks for
 * izin, sakit (with the surat dokter), cuti, or a tukar shift with a colleague.
 * A tukar shift first waits for the pengganti to confirm. The approver — any
 * holder of operator.absensi.write at the unit (Koordinator Operasi, Project
 * Leader), never for their own request — approves (the Jadwal Shift is
 * updated, see {@see AttendanceRequestService}) or rejects. The employee may
 * cancel while it is pending; the approver may cancel an approval, which puts
 * the previous codes back.
 *
 * Who submits: operator.presensi (the field roles) with an employee record on
 * the unit's roster. Who sees the whole unit: the approvers and the read-only
 * supervisors (operator.absensi.view without presensi, e.g. TL Operasi);
 * everybody else sees their own requests and those they substitute in.
 */
class PengajuanController extends Controller
{
    /** Attachment: surat dokter / bukti — images or PDF, kept off the public disk. */
    private const ATTACHMENT_DISK = 'local';

    public function __construct(
        private readonly AttendanceRequestService $requests,
        private readonly PresenceRecorder $presence,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorPresensi) || $user->hasPermissionTo(PermissionName::OperatorAbsensiView), 403);

        $me = $this->presence->employeeOf($user);
        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty() && $me === null, 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id'))
            ?? ($me !== null ? $units->firstWhere('id', $me->unit_id) : null)
            ?? $units->first();
        abort_unless($unit !== null && $user->canAccessUnit($unit), 403);

        $now = Carbon::now(PresenceRecorder::TIMEZONE);
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = max(2000, min(2100, (int) ($request->integer('year') ?: $now->year)));
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();

        $seesAll = $this->seesAll($user);
        $mine = $me !== null && $me->unit_id === $unit->id ? $me : null;

        $list = AttendanceRequest::query()->with(['employee:id,name,regu,position', 'substitute:id,name,regu', 'decider:id,name'])
            ->where('unit_id', $unit->id)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->whereDate('start_date', '<=', $to->toDateString())->whereDate('end_date', '>=', $from->toDateString()))
                // Pending requests always stay in view, whatever the month.
                ->orWhereIn('status', [AttendanceRequestStatus::MenungguPengganti->value, AttendanceRequestStatus::MenungguPersetujuan->value]))
            ->when(! $seesAll, fn ($query) => $query->where(fn ($q) => $q
                ->where('employee_id', $mine?->id ?? 0)
                ->orWhere('substitute_employee_id', $mine?->id ?? 0)))
            ->orderByDesc('created_at')
            ->get();

        $roster = $this->requests->rosterOf($unit);
        $canSubmit = $mine !== null && $user->hasPermissionTo(PermissionName::OperatorPresensi) && $roster->contains('id', $mine->id) && ! $user->isReadOnly();

        // Schedule codes from this month to the end of next month, to pick a shift to swap and a free pengganti.
        $codes = $canSubmit
            ? $this->requests->codesBetween($unit, $roster->pluck('id')->all(), $now->copy()->startOfMonth(), $now->copy()->addMonthNoOverflow()->endOfMonth())
            : [];

        return Inertia::render('operator/pengajuan/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 1, $now->year + 1),
                'types' => collect(AttendanceRequestType::cases())->map(fn (AttendanceRequestType $t): array => ['value' => $t->value, 'label' => $t->label(), 'code' => $t->attendanceCode()])->all(),
                'max_days' => AttendanceRequestService::MAX_DAYS,
            ],
            'me' => $mine === null ? null : ['id' => $mine->id, 'name' => $mine->name, 'regu' => $mine->regu, 'codes' => (object) ($codes[$mine->id] ?? [])],
            'colleagues' => $canSubmit
                ? $roster->reject(fn (Employee $e): bool => $e->id === $mine->id)->map(fn (Employee $e): array => [
                    'id' => $e->id, 'name' => $e->name, 'regu' => $e->regu, 'position' => $e->position, 'codes' => (object) ($codes[$e->id] ?? []),
                ])->values()->all()
                : [],
            'requests' => $list->map(fn (AttendanceRequest $r): array => $this->present($r, $user, $mine))->all(),
            'can_submit' => $canSubmit,
            'can_approve' => $this->canApprove($user, $unit),
            'sees_all' => $seesAll,
            'today' => $now->toDateString(),
        ]);
    }

    public function store(Request $request, EventNotifier $events): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorPresensi), 403);

        $employee = $this->presence->employeeOf($user);
        abort_if($employee === null || $employee->unit === null, 403, 'Akun Anda belum terhubung ke data pegawai.');
        $unit = $employee->unit;
        abort_unless($user->canAccessUnit($unit) && $this->requests->onRoster($unit, $employee), 403, 'Anda tidak terdaftar di jadwal shift unit ini.');

        $validated = $request->validate([
            'type' => ['required', Rule::in(AttendanceRequestType::values())],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'substitute_employee_id' => ['nullable', 'required_if:type,tukar_shift', 'integer'],
            'swap_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $type = AttendanceRequestType::from($validated['type']);
        $tukar = $type->needsSubstitute();
        $substitute = $tukar ? Employee::query()->with('user')->find($validated['substitute_employee_id']) : null;
        $data = [
            'type' => $type,
            'start_date' => $validated['start_date'],
            // A tukar shift is one shift; the other requests may span days.
            'end_date' => $tukar ? $validated['start_date'] : ($validated['end_date'] ?? $validated['start_date']),
            'substitute' => $substitute,
            'swap_date' => $tukar ? ($validated['swap_date'] ?? null) : null,
        ];
        $this->requests->validateNew($unit, $employee, $data);

        $attendance = DB::transaction(function () use ($request, $user, $employee, $unit, $validated, $data, $tukar, $substitute): AttendanceRequest {
            return AttendanceRequest::query()->create([
                'unit_id' => $unit->id,
                'employee_id' => $employee->id,
                'requested_by' => $user->id,
                'type' => $data['type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => trim($validated['reason']),
                'attachment_path' => $request->file('attachment')?->store("attendance-requests/{$unit->id}", self::ATTACHMENT_DISK),
                'substitute_employee_id' => $substitute?->id,
                'swap_date' => $data['swap_date'],
                // A pengganti without an account cannot confirm: the approver decides straight away.
                'status' => $tukar && $substitute?->user_id !== null ? AttendanceRequestStatus::MenungguPengganti : AttendanceRequestStatus::MenungguPersetujuan,
            ]);
        });

        $events->attendanceRequestSubmitted($attendance, $user);
        $this->activityLogger->log(ActivityEvent::Created, "Mengajukan {$type->label()} ({$employee->name})", $attendance, unit: $unit->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => $attendance->status === AttendanceRequestStatus::MenungguPengganti
            ? 'Pengajuan tukar shift terkirim. Menunggu konfirmasi '.$substitute?->name.'.'
            : 'Pengajuan terkirim. Menunggu persetujuan.']);

        return back();
    }

    /**
     * The pengganti accepts or declines a tukar shift.
     */
    public function respond(Request $request, AttendanceRequest $attendanceRequest, EventNotifier $events): RedirectResponse
    {
        $user = $request->user();
        $me = $this->presence->employeeOf($user);
        abort_unless($me !== null && $attendanceRequest->substitute_employee_id === $me->id, 403);
        abort_unless($attendanceRequest->status === AttendanceRequestStatus::MenungguPengganti, 422, 'Permintaan ini sudah dijawab.');

        $validated = $request->validate([
            'accept' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $accepted = (bool) $validated['accept'];

        $attendanceRequest->update([
            'status' => $accepted ? AttendanceRequestStatus::MenungguPersetujuan : AttendanceRequestStatus::Ditolak,
            'substitute_responded_at' => now(),
            'substitute_note' => $validated['note'] ?? null,
        ]);

        $events->attendanceSubstituteResponded($attendanceRequest, $accepted, $user);
        $this->activityLogger->log(ActivityEvent::Updated, ($accepted ? 'Bersedia' : 'Menolak').' menggantikan shift pengajuan #'.$attendanceRequest->id, $attendanceRequest, unit: $attendanceRequest->unit_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => $accepted ? 'Anda bersedia menggantikan. Menunggu persetujuan atasan.' : 'Permintaan tukar shift ditolak.']);

        return back();
    }

    public function approve(Request $request, AttendanceRequest $attendanceRequest, EventNotifier $events): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeDecision($user, $attendanceRequest);
        abort_unless($attendanceRequest->status === AttendanceRequestStatus::MenungguPersetujuan, 422, 'Pengajuan ini tidak sedang menunggu persetujuan.');

        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        DB::transaction(function () use ($attendanceRequest, $user, $note): void {
            $this->requests->apply($attendanceRequest, $user->id);
            $attendanceRequest->update([
                'status' => AttendanceRequestStatus::Disetujui,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);
        });

        $events->attendanceRequestDecided($attendanceRequest, $user);
        $this->activityLogger->log(ActivityEvent::Updated, "Menyetujui pengajuan {$attendanceRequest->type->label()} #{$attendanceRequest->id}", $attendanceRequest, unit: $attendanceRequest->unit_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan disetujui. Jadwal Shift sudah diperbarui.']);

        return back();
    }

    public function reject(Request $request, AttendanceRequest $attendanceRequest, EventNotifier $events): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeDecision($user, $attendanceRequest);
        abort_unless($attendanceRequest->status->isPending(), 422, 'Pengajuan ini sudah diputuskan.');

        $note = $request->validate(['note' => ['required', 'string', 'min:3', 'max:255']])['note'];

        $attendanceRequest->update([
            'status' => AttendanceRequestStatus::Ditolak,
            'decided_by' => $user->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        $events->attendanceRequestDecided($attendanceRequest, $user);
        $this->activityLogger->log(ActivityEvent::Updated, "Menolak pengajuan {$attendanceRequest->type->label()} #{$attendanceRequest->id}", $attendanceRequest, unit: $attendanceRequest->unit_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan ditolak.']);

        return back();
    }

    /**
     * The employee withdraws a pending request; the approver undoes an
     * approved one (the Jadwal Shift gets its previous codes back).
     */
    public function cancel(Request $request, AttendanceRequest $attendanceRequest, EventNotifier $events): RedirectResponse
    {
        $user = $request->user();
        $me = $this->presence->employeeOf($user);
        $own = $me !== null && $attendanceRequest->employee_id === $me->id;

        if ($attendanceRequest->status->isPending()) {
            abort_unless($own || $this->canApprove($user, $attendanceRequest->unit_id), 403);
        } else {
            abort_unless($attendanceRequest->status === AttendanceRequestStatus::Disetujui, 422, 'Pengajuan ini sudah selesai.');
            $this->authorizeDecision($user, $attendanceRequest);
        }

        $wasApproved = $attendanceRequest->status === AttendanceRequestStatus::Disetujui;

        DB::transaction(function () use ($attendanceRequest, $user, $wasApproved): void {
            if ($wasApproved) {
                $this->requests->revert($attendanceRequest);
            }

            $attendanceRequest->update([
                'status' => AttendanceRequestStatus::Dibatalkan,
                'decided_by' => $wasApproved ? $user->id : $attendanceRequest->decided_by,
                'decided_at' => $wasApproved ? now() : $attendanceRequest->decided_at,
            ]);
        });

        if ($wasApproved || ! $own) {
            $events->attendanceRequestDecided($attendanceRequest, $user);
        }
        $this->activityLogger->log(ActivityEvent::Updated, "Membatalkan pengajuan {$attendanceRequest->type->label()} #{$attendanceRequest->id}", $attendanceRequest, unit: $attendanceRequest->unit_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => $wasApproved ? 'Persetujuan dibatalkan. Jadwal Shift dikembalikan.' : 'Pengajuan dibatalkan.']);

        return back();
    }

    /**
     * The surat dokter / bukti, for the employee, the pengganti and whoever sees the whole unit.
     */
    public function attachment(Request $request, AttendanceRequest $attendanceRequest): StreamedResponse
    {
        $user = $request->user();
        $me = $this->presence->employeeOf($user);
        $involved = $me !== null && in_array($me->id, [$attendanceRequest->employee_id, $attendanceRequest->substitute_employee_id], true);

        abort_unless($involved || ($this->seesAll($user) && $user->canAccessUnit($attendanceRequest->unit_id)), 403);
        abort_unless($attendanceRequest->attachment_path !== null && Storage::disk(self::ATTACHMENT_DISK)->exists($attendanceRequest->attachment_path), 404);

        return Storage::disk(self::ATTACHMENT_DISK)->response($attendanceRequest->attachment_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AttendanceRequest $r, User $user, ?Employee $me): array
    {
        $own = $me !== null && $r->employee_id === $me->id;
        $canApprove = $this->canApprove($user, $r->unit_id) && ! $own;

        return [
            'id' => $r->id,
            'type' => $r->type->value,
            'type_label' => $r->type->label(),
            'employee' => ['id' => $r->employee?->id, 'name' => $r->employee?->name, 'regu' => $r->employee?->regu],
            'substitute' => $r->substitute === null ? null : ['id' => $r->substitute->id, 'name' => $r->substitute->name, 'regu' => $r->substitute->regu],
            'start_date' => $r->start_date->toDateString(),
            'end_date' => $r->end_date->toDateString(),
            'swap_date' => $r->swap_date?->toDateString(),
            'days' => $r->start_date->diffInDays($r->end_date) + 1,
            'reason' => $r->reason,
            'has_attachment' => $r->attachment_path !== null,
            'attachment_url' => $r->attachment_path !== null ? route('operator.pengajuan.attachment', $r, false) : null,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'status_tone' => $r->status->tone(),
            'substitute_note' => $r->substitute_note,
            'decided_by' => $r->decider?->name,
            'decided_at' => $r->decided_at?->timezone(PresenceRecorder::TIMEZONE)->format('Y-m-d H:i'),
            'decision_note' => $r->decision_note,
            'created_at' => $r->created_at?->timezone(PresenceRecorder::TIMEZONE)->format('Y-m-d H:i'),
            'applied' => collect((array) $r->applied)->map(fn (array $cell): array => [
                ...$cell,
                'employee' => $cell['employee_id'] === $r->employee_id ? $r->employee?->name : $r->substitute?->name,
            ])->all(),
            'is_mine' => $own,
            'can' => [
                'respond' => $me !== null && $r->substitute_employee_id === $me->id && $r->status === AttendanceRequestStatus::MenungguPengganti,
                'approve' => $canApprove && $r->status === AttendanceRequestStatus::MenungguPersetujuan,
                'reject' => $canApprove && $r->status->isPending(),
                'cancel' => ($r->status->isPending() && ($own || $canApprove)) || ($r->status === AttendanceRequestStatus::Disetujui && $canApprove),
            ],
        ];
    }

    private function canApprove(User $user, Unit|int $unit): bool
    {
        return $user->hasPermissionTo(PermissionName::OperatorAbsensiWrite) && $user->canAccessUnit($unit) && ! $user->isReadOnly();
    }

    /** Approvers, and the read-only supervisors who hold absensi.view without being on a roster themselves. */
    private function seesAll(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::OperatorAbsensiWrite)
            || ($user->hasPermissionTo(PermissionName::OperatorAbsensiView) && ! $user->hasPermissionTo(PermissionName::OperatorPresensi));
    }

    private function authorizeDecision(User $user, AttendanceRequest $attendanceRequest): void
    {
        abort_unless($this->canApprove($user, $attendanceRequest->unit_id), 403);

        $me = $this->presence->employeeOf($user);
        abort_if($me !== null && $attendanceRequest->employee_id === $me->id, 403, 'Pengajuan Anda sendiri disetujui oleh atasan lain.');
    }
}
