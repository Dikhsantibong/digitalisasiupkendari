<?php

namespace App\Services\Reports;

use App\Enums\AttendanceRequestStatus;
use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Models\AttendanceRequest;
use App\Models\ReportWorkflow;
use App\Models\ReportWorkflowStep;
use App\Models\Unit;
use App\Models\User;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The approval inbox of an account that approves something:
 * - the reports whose open step of the workflow is theirs — Koordinator
 *   (verifikasi), Team Leader (setujui) or Manager UL (sahkan) — and the ones
 *   they signed (actions post to the existing report-workflow.* routes);
 * - the operators' pengajuan (izin / cuti / tukar shift) waiting for a
 *   decision, for whoever approves them (operator.absensi.write, e.g. the
 *   Project Leader; actions post to operator.pengajuan.approve / reject);
 * - the Laporan Project the Project Leader / Office signs "Mengetahui" in
 *   (no action: the signature prints once the report is FINAL).
 * Shown on the Manager UL home, the dashboard of Team Leader / Koordinator /
 * Project Leader and the Persetujuan page.
 */
class ApprovalInbox
{
    /** Whether the user's employee holds a jabatan that signs a report (chain or "Mengetahui"). */
    public static function isSigner(User $user): bool
    {
        $position = $user->employee?->canonicalPosition();

        return $position !== null && (
            $position === EmployeePosition::ManagerUl
            || $position === EmployeePosition::ProjectLeader
            || str_starts_with($position->name, 'TeamLeader')
            || str_starts_with($position->name, 'Koordinator')
        );
    }

    /** Whether the user decides the operators' pengajuan (izin / cuti / tukar shift). */
    public static function decidesRequests(User $user): bool
    {
        return ! $user->isReadOnly() && $user->hasPermissionTo(PermissionName::OperatorAbsensiWrite);
    }

    /** Whether the account has an approval inbox at all. */
    public static function applies(User $user): bool
    {
        return ! $user->isSuperAdmin() && (self::isSigner($user) || self::decidesRequests($user));
    }

    /** How many things wait for the user's decision (null when the account has no inbox). */
    public static function pendingCount(User $user): ?int
    {
        if (! self::applies($user)) {
            return null;
        }

        $inbox = app(self::class);

        return count($inbox->queue($user)) + count($inbox->requests($user));
    }

    /**
     * The operators' pengajuan waiting for the user's decision, in the units
     * they can access (never their own).
     *
     * @return list<array<string, mixed>>
     */
    public function requests(User $user): array
    {
        if (! self::decidesRequests($user)) {
            return [];
        }

        $own = $user->employee?->id;

        return AttendanceRequest::query()
            ->where('status', AttendanceRequestStatus::MenungguPersetujuan)
            ->whereIn('unit_id', $user->accessibleUnitIds())
            ->when($own !== null, fn ($q) => $q->where('employee_id', '!=', $own))
            ->with(['employee:id,name,regu', 'substitute:id,name,regu', 'unit:id,name'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (AttendanceRequest $r): array => [
                'id' => $r->id,
                'type_label' => $r->type->label(),
                'unit' => $r->unit?->name ?? '-',
                'employee' => $r->employee?->name ?? '-',
                'regu' => $r->employee?->regu,
                'substitute' => $r->substitute?->name,
                'start_date' => $r->start_date->toDateString(),
                'end_date' => $r->end_date->toDateString(),
                'days' => (int) $r->start_date->diffInDays($r->end_date) + 1,
                'reason' => $r->reason,
                'created_at' => $r->created_at?->toIso8601String(),
                'url' => route('operator.pengajuan.index', ['unit_id' => $r->unit_id], false),
            ])->values()->all();
    }

    /**
     * Laporan Project the user signs "Mengetahui" / "Dibuat" in (the
     * in-report block, e.g. the Project Leader), latest first.
     *
     * @return list<array<string, mixed>>
     */
    public function acknowledgements(User $user): array
    {
        $employee = $user->employee;
        if ($employee === null) {
            return [];
        }

        return ReportWorkflowStep::query()
            ->where('employee_id', $employee->id)
            ->where('stage', ReportWorkflowStep::STAGE_TANDA_TANGAN)
            ->whereHas('workflow', fn ($q) => $q->where('status', '!=', ReportStatus::Draft->value))
            ->with('workflow.unit:id,name')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (ReportWorkflowStep $s): array => [
                'key' => $s->id,
                'unit' => $s->workflow->unit?->name ?? '-',
                'title' => $s->workflow->module->label(),
                'period' => Indonesian::monthName($s->workflow->month).' '.$s->workflow->year,
                'caption' => $s->caption,
                'status' => $s->workflow->module->statusLabel($s->workflow->status),
                'final' => $s->workflow->status === ReportStatus::Final,
                ...self::links($s->workflow->module, $s->workflow->unit_id, $s->workflow->month, $s->workflow->year),
            ])->values()->all();
    }

    /**
     * Reports whose open step is the user's own, oldest first, with the
     * signers so far and the action the user takes (verify / approve / ratify).
     *
     * @param  Collection<int, Unit>|null  $units  for the unit names (looked up otherwise)
     * @return list<array<string, mixed>>
     */
    public function queue(User $user, ?Collection $units = null): array
    {
        $employee = $user->employee;
        if ($employee === null) {
            return [];
        }

        $names = $units?->pluck('name', 'id') ?? collect();
        $actions = [1 => 'verify', 2 => 'approve', 3 => 'ratify'];

        return ReportWorkflow::query()
            ->whereIn('status', [ReportStatus::Diajukan->value, ReportStatus::Verifikasi->value, ReportStatus::Disetujui->value])
            ->whereHas('steps', fn ($q) => $q->where('employee_id', $employee->id)->whereNull('signed_at'))
            ->with(['steps.employee:id,name', 'submitter:id,name'])
            ->orderBy('submitted_at')
            ->get()
            ->map(function (ReportWorkflow $w) use ($employee, $names, $actions): ?array {
                $sequence = match ($w->status) {
                    ReportStatus::Diajukan => 1,
                    ReportStatus::Verifikasi => 2,
                    default => 3,
                };
                $mine = $w->steps->first(fn (ReportWorkflowStep $s): bool => $s->isPengesahan() && $s->sequence === $sequence && $s->employee_id === $employee->id);
                if ($mine === null) {
                    return null;
                }
                $since = $w->steps->where('sequence', $sequence - 1)->first()?->signed_at ?? $w->submitted_at;

                return [
                    'key' => $w->id,
                    'module' => $w->module->value,
                    'unit_id' => $w->unit_id,
                    'month' => $w->month,
                    'year' => $w->year,
                    'unit' => $names[$w->unit_id] ?? Unit::query()->whereKey($w->unit_id)->value('name') ?? '-',
                    'title' => $w->module->label(),
                    'period' => Indonesian::monthName($w->month).' '.$w->year,
                    'status' => $w->module->statusLabel($w->status),
                    'action' => $actions[$sequence],
                    'action_label' => $mine->caption,
                    'submitted_by' => $w->submitter?->name,
                    'submitted_at' => $w->submitted_at?->toIso8601String(),
                    'waiting_days' => $since === null ? 0 : (int) $since->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay()),
                    'steps' => $w->steps->where('stage', ReportWorkflowStep::STAGE_PENGESAHAN)->sortBy('sequence')->map(fn (ReportWorkflowStep $s): array => [
                        'caption' => $s->caption,
                        'position' => $s->position,
                        'name' => $s->employee?->name,
                        'signed_at' => $s->signed_at?->toIso8601String(),
                        'current' => $s->is($mine),
                    ])->values()->all(),
                    ...self::links($w->module, $w->unit_id, $w->month, $w->year),
                ];
            })
            ->filter()->values()->all();
    }

    /**
     * The last reports the user signed.
     *
     * @param  Collection<int, Unit>|null  $units  for the unit names (looked up otherwise)
     * @return list<array<string, mixed>>
     */
    public function history(User $user, ?Collection $units = null): array
    {
        $employee = $user->employee;
        if ($employee === null) {
            return [];
        }

        $names = $units?->pluck('name', 'id') ?? collect();

        return ReportWorkflowStep::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('signed_at')
            ->with('workflow')
            ->latest('signed_at')
            ->limit(12)
            ->get()
            ->filter(fn (ReportWorkflowStep $s): bool => $s->workflow !== null)
            ->map(fn (ReportWorkflowStep $s): array => [
                'key' => $s->id,
                'unit' => $names[$s->workflow->unit_id] ?? Unit::query()->whereKey($s->workflow->unit_id)->value('name') ?? '-',
                'title' => $s->workflow->module->label(),
                'period' => Indonesian::monthName($s->workflow->month).' '.$s->workflow->year,
                'caption' => $s->caption,
                'signed_at' => $s->signed_at?->toIso8601String(),
                'status' => $s->workflow->module->statusLabel($s->workflow->status),
                'final' => $s->workflow->status === ReportStatus::Final,
                ...self::links($s->workflow->module, $s->workflow->unit_id, $s->workflow->month, $s->workflow->year),
            ])->values()->all();
    }

    /**
     * @return array{view_url: string, download_url: string, document_url: string}
     */
    public static function links(ReportModule $module, int $unitId, int $month, int $year): array
    {
        return [
            'view_url' => $module->pdfUrl($unitId, $month, $year),
            'download_url' => $module->pdfUrl($unitId, $month, $year, ['download' => 1]),
            'document_url' => $module->documentUrl($unitId, $month, $year),
        ];
    }
}
