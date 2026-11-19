<?php

namespace App\Services\Reports;

use App\Enums\EmployeePosition;
use App\Enums\ReportStatus;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\UlReportDocument;
use App\Models\UlReportStep;
use App\Models\Unit;
use App\Models\User;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approval of a Laporan UL (user, 2026-11-18), apart from the unit reports
 * ({@see ReportWorkflowService}): the Admin UL submits the saved document,
 * one Team Leader per bidang approves it in turn, the Manager UL ratifies it
 * (FINAL). The approvers are the jabatan holders of the UL's induk unit (its
 * first active unit), frozen at submission; a "Penanda Tangan Laporan"
 * delegation of that unit moves a jabatan to another unit's holder.
 */
class UlReportWorkflow
{
    /**
     * kind => the TL bidang approving in turn (then the Manager UL).
     *
     * @var array<string, list<EmployeePosition>>
     */
    public const CHAINS = [
        // Laporan Pengusahaan Pembangkit: dibuat TL Operasi, disahkan Manager UL (user, 2026-11-18).
        'pengusahaan' => [EmployeePosition::TeamLeaderOperasi],
        'logistik' => [EmployeePosition::TeamLeaderPemeliharaan],
        'k3l' => [EmployeePosition::TeamLeaderK3],
    ];

    /**
     * Reports whose TL step is the maker ("Membuat" / Dibuat Oleh) rather than an
     * approver: the Admin UL only prepares & submits and is not printed.
     *
     * @var list<string>
     */
    public const TL_MAKES = ['pengusahaan'];

    /** The first approval step (the panel has no Koordinator step 1). */
    private const FIRST_SEQUENCE = 2;

    public function __construct(private readonly ReportSignatories $signatories) {}

    public function find(ServiceUnit $ul, string $kind, int $month, int $year): ?UlReportDocument
    {
        return UlReportDocument::query()
            ->where('service_unit_id', $ul->id)->where('kind', $kind)->where('month', $month)->where('year', $year)
            ->with(['steps.employee:id,name', 'steps.signer:id,name', 'logs', 'submitter:id,name', 'rejecter:id,name'])
            ->first();
    }

    public function status(?UlReportDocument $document): ReportStatus
    {
        return $document?->status ?? ReportStatus::Draft;
    }

    public static function statusLabel(ReportStatus $status): string
    {
        return match ($status) {
            ReportStatus::Draft => 'Draft',
            ReportStatus::Diajukan => 'Diajukan — Menunggu Persetujuan Team Leader',
            ReportStatus::Disetujui => 'Disetujui TL — Menunggu Pengesahan Manager UL',
            ReportStatus::Final, ReportStatus::Disahkan => 'Final — Disahkan Manager UL',
            ReportStatus::Ditolak => 'Ditolak — Perlu Perbaikan',
            default => $status->label(),
        };
    }

    /** The UL's induk unit: its first active unit (whose jabatan holders approve). */
    public function indukUnit(ServiceUnit $ul): ?Unit
    {
        return $ul->units()->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * The approval chain as it would be frozen now: TL per bidang, then the Manager UL.
     *
     * @return list<array{sequence: int, caption: string, position: EmployeePosition, employee: Employee|null}>
     */
    public function chain(ServiceUnit $ul, string $kind): array
    {
        $induk = $this->indukUnit($ul);
        $positions = [...self::CHAINS[$kind], EmployeePosition::ManagerUl];

        return array_map(fn (EmployeePosition $position, int $i): array => [
            'sequence' => self::FIRST_SEQUENCE + $i,
            'caption' => $position === EmployeePosition::ManagerUl ? 'Mengesahkan' : (in_array($kind, self::TL_MAKES, true) ? 'Membuat' : 'Menyetujui'),
            // The jabatan holding it at the induk unit (TL Ophar / Project Leader by its TL structure).
            'position' => $induk === null ? $position : $this->signatories->effectivePosition($induk, $position),
            'employee' => $induk === null ? null : $this->signatories->holder($induk, $position),
        ], $positions, array_keys($positions));
    }

    /** Whether the user's employee approves this report (frozen steps, else the current chain). */
    public function isApprover(User $user, ServiceUnit $ul, string $kind, ?UlReportDocument $document): bool
    {
        $employeeId = $user->employee?->id;
        if ($employeeId === null) {
            return false;
        }

        $ids = $document !== null && $document->steps->isNotEmpty()
            ? $document->steps->pluck('employee_id')
            : collect($this->chain($ul, $kind))->map(fn (array $step): ?int => $step['employee']?->id);

        return $ids->contains($employeeId);
    }

    /** The open step the user may act on now (only the frozen employee of that step). */
    public function actionableStep(User $user, ?UlReportDocument $document): ?UlReportStep
    {
        if ($document === null || ! in_array($document->status, [ReportStatus::Diajukan, ReportStatus::Disetujui], true)) {
            return null;
        }

        $step = $document->steps->first(fn (UlReportStep $s): bool => $s->signed_at === null);
        if ($step === null || $step->employee_id === null) {
            return null;
        }

        $employee = $user->employee;

        return $employee !== null && $employee->is_active && $employee->id === $step->employee_id ? $step : null;
    }

    public function isManagerStep(?UlReportStep $step): bool
    {
        return $step !== null && $step->position === EmployeePosition::ManagerUl->value;
    }

    public function canSubmit(User $user, ?UlReportDocument $document): bool
    {
        return $this->status($document)->canBeSubmitted() && ! $user->isReadOnly() && UlReport::allows($user);
    }

    /**
     * Ajukan: freeze the approvers and send the saved document to the first TL.
     */
    public function submit(User $user, ServiceUnit $ul, string $kind, int $month, int $year, UlReportDocument $document, ?string $note = null): UlReportDocument
    {
        abort_unless($this->canSubmit($user, $document), 403);

        $chain = $this->chain($ul, $kind);
        $missing = array_filter($chain, fn (array $step): bool => $step['employee'] === null);
        if ($missing !== []) {
            throw ValidationException::withMessages(['workflow' => 'Belum ada pejabat untuk: '.implode(', ', array_map(fn (array $s): string => $s['position']->value, $missing)).'. Lengkapi di Data Pegawai unit induk UL atau atur di menu Penanda Tangan Laporan.']);
        }

        return DB::transaction(function () use ($user, $document, $chain, $note): UlReportDocument {
            $from = $this->status($document);
            $document->steps()->delete();
            foreach ($chain as $step) {
                $document->steps()->create([
                    'sequence' => $step['sequence'],
                    'caption' => $step['caption'],
                    'position' => $step['position']->value,
                    'employee_id' => $step['employee']->id,
                ]);
            }

            $document->fill([
                'status' => ReportStatus::Diajukan,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'finalized_at' => null,
            ])->save();
            $this->log($document, $user, $from === ReportStatus::Ditolak ? 'ajukan_kembali' : 'ajukan', $from, $note, 'Admin UL');

            return $document->fresh(['steps']);
        });
    }

    /** Setujui (a TL) — the last TL moves the report on to the Manager UL. */
    public function approve(User $user, UlReportDocument $document, ?string $note = null): UlReportDocument
    {
        $step = $this->actionableStep($user, $document);
        abort_if($step === null || $this->isManagerStep($step), 403);

        return DB::transaction(function () use ($user, $document, $step, $note): UlReportDocument {
            $from = $document->status;
            $step->fill(['signed_by' => $user->id, 'signed_at' => now(), 'note' => $note])->save();
            $next = $document->steps()->whereNull('signed_at')->orderBy('sequence')->first();
            $document->fill(['status' => $this->isManagerStep($next) ? ReportStatus::Disetujui : ReportStatus::Diajukan])->save();
            $this->log($document, $user, 'setujui', $from, $note, $step->position);

            return $document->fresh(['steps']);
        });
    }

    /** Sahkan (Manager UL) — the report becomes FINAL. */
    public function ratify(User $user, UlReportDocument $document, ?string $note = null): UlReportDocument
    {
        $step = $this->actionableStep($user, $document);
        abort_unless($this->isManagerStep($step) && $document->status === ReportStatus::Disetujui, 403);

        return DB::transaction(function () use ($user, $document, $step, $note): UlReportDocument {
            $from = $document->status;
            $step->fill(['signed_by' => $user->id, 'signed_at' => now(), 'note' => $note])->save();
            $document->fill(['status' => ReportStatus::Final, 'finalized_at' => now()])->save();
            $this->log($document, $user, 'sahkan', $from, $note, $step->position);

            return $document->fresh(['steps']);
        });
    }

    /** Tolak — only the approver whose step is open; the Admin UL fixes and resubmits. */
    public function reject(User $user, UlReportDocument $document, string $reason): UlReportDocument
    {
        $step = $this->actionableStep($user, $document);
        abort_if($step === null, 403);

        return DB::transaction(function () use ($user, $document, $step, $reason): UlReportDocument {
            $from = $document->status;
            $document->fill(['status' => ReportStatus::Ditolak, 'rejected_by' => $user->id, 'rejected_at' => now(), 'rejection_reason' => $reason])->save();
            $this->log($document, $user, 'tolak', $from, $reason, $step->position);

            return $document->fresh(['steps']);
        });
    }

    /**
     * The panel state (same shape as ReportWorkflowService::present, read by
     * components/document/report-workflow-panel.tsx), with the UL action URLs.
     *
     * @return array<string, mixed>
     */
    public function present(User $user, ServiceUnit $ul, string $kind, int $month, int $year, ?UlReportDocument $document): array
    {
        $status = $this->status($document);
        $step = $this->actionableStep($user, $document);
        $frozen = $document !== null && $document->steps->isNotEmpty();
        $query = ['ul_id' => $ul->id, 'month' => $month, 'year' => $year];

        $steps = $frozen
            ? $document->steps->map(fn (UlReportStep $s): array => [
                'stage' => 'pengesahan',
                'sequence' => $s->sequence,
                'caption' => $s->caption,
                'position' => $s->position,
                'name' => $s->employee?->name,
                'signed' => $s->signed_at !== null,
                'signed_at' => $s->signed_at?->toIso8601String(),
                'signed_by' => $s->signer?->name,
                'current' => $s->is($document->steps->first(fn (UlReportStep $x): bool => $x->signed_at === null)) && in_array($status, [ReportStatus::Diajukan, ReportStatus::Disetujui], true),
            ])->values()->all()
            : array_map(fn (array $s): array => [
                'stage' => 'pengesahan',
                'sequence' => $s['sequence'],
                'caption' => $s['caption'],
                'position' => $s['position']->value,
                'name' => $s['employee']?->name,
                'signed' => false,
                'signed_at' => null,
                'signed_by' => null,
                'current' => false,
            ], $this->chain($ul, $kind));

        return [
            'module' => "ul-{$kind}",
            'status' => $status->value,
            'status_label' => self::statusLabel($status),
            'status_tone' => $status->tone(),
            'editable' => $status->isEditable(),
            'submitted' => $document?->submitted_at ? ['by' => $document->submitter?->name, 'at' => $document->submitted_at->toIso8601String()] : null,
            'verification' => null,
            'rejection' => $document?->rejected_at ? [
                'by' => $document->rejecter?->name,
                'at' => $document->rejected_at->toIso8601String(),
                'reason' => $document->rejection_reason,
                'active' => $status === ReportStatus::Ditolak,
            ] : null,
            'steps' => $steps,
            'logs' => $document?->logs->map(fn ($log): array => [
                'id' => $log->id,
                'user' => $log->user_name,
                'jabatan' => $log->jabatan,
                'action' => $log->action,
                'from' => $log->from_status ? self::statusLabel(ReportStatus::from($log->from_status)) : null,
                'to' => self::statusLabel(ReportStatus::from($log->to_status)),
                'note' => $log->note,
                'at' => $log->created_at?->toIso8601String(),
            ])->reverse()->values()->all() ?? [],
            'can' => [
                'submit' => $this->canSubmit($user, $document),
                'verify' => false,
                'approve' => $step !== null && ! $this->isManagerStep($step),
                'ratify' => $this->isManagerStep($step) && $status === ReportStatus::Disetujui,
                'reject' => $step !== null,
            ],
            'action_urls' => [
                'submit' => route('laporan-ul.workflow.submit', ['kind' => $kind, ...$query]),
                'approve' => route('laporan-ul.workflow.approve', ['kind' => $kind, ...$query]),
                'ratify' => route('laporan-ul.workflow.ratify', ['kind' => $kind, ...$query]),
                'reject' => route('laporan-ul.workflow.reject', ['kind' => $kind, ...$query]),
            ],
        ];
    }

    /**
     * The Laporan UL waiting for the user's decision (Persetujuan inbox), in
     * the shape of ApprovalInbox::queue items plus their own action URLs.
     *
     * @return list<array<string, mixed>>
     */
    public function queue(User $user): array
    {
        $employee = $user->employee;
        if ($employee === null) {
            return [];
        }

        return UlReportDocument::query()
            ->whereIn('status', [ReportStatus::Diajukan->value, ReportStatus::Disetujui->value])
            ->whereHas('steps', fn ($q) => $q->where('employee_id', $employee->id)->whereNull('signed_at'))
            ->with(['steps.employee:id,name', 'submitter:id,name', 'serviceUnit:id,name'])
            ->orderBy('submitted_at')
            ->get()
            ->map(function (UlReportDocument $document) use ($user): ?array {
                $step = $this->actionableStep($user, $document);
                if ($step === null) {
                    return null;
                }

                $previous = $document->steps->where('sequence', $step->sequence - 1)->first()?->signed_at;
                $since = $previous ?? $document->submitted_at;
                $query = ['kind' => $document->kind, 'ul_id' => $document->service_unit_id, 'month' => $document->month, 'year' => $document->year];
                $manager = $this->isManagerStep($step);

                return [
                    'key' => 'ul-'.$document->id,
                    'module' => 'ul-'.$document->kind,
                    'unit_id' => 0,
                    'month' => $document->month,
                    'year' => $document->year,
                    'unit' => $document->serviceUnit->name,
                    'title' => UlReport::KINDS[$document->kind][0].' (Laporan UL)',
                    'period' => Indonesian::monthName($document->month).' '.$document->year,
                    'status' => self::statusLabel($document->status),
                    'action' => $manager ? 'ratify' : 'approve',
                    'action_label' => $step->caption,
                    'submitted_by' => $document->submitter?->name,
                    'submitted_at' => $document->submitted_at?->toIso8601String(),
                    'waiting_days' => $since === null ? 0 : (int) $since->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay()),
                    'steps' => $document->steps->map(fn (UlReportStep $s): array => [
                        'caption' => $s->caption,
                        'position' => $s->position,
                        'name' => $s->employee?->name,
                        'signed_at' => $s->signed_at?->toIso8601String(),
                        'current' => $s->is($step),
                    ])->values()->all(),
                    'view_url' => route('laporan-ul.pdf', $query),
                    'download_url' => route('laporan-ul.pdf', [...$query, 'download' => 1]),
                    'document_url' => route('laporan-ul.show', $query),
                    'action_url' => route($manager ? 'laporan-ul.workflow.ratify' : 'laporan-ul.workflow.approve', $query),
                    'reject_url' => route('laporan-ul.workflow.reject', $query),
                ];
            })
            ->filter()->values()->all();
    }

    private function log(UlReportDocument $document, User $user, string $action, ?ReportStatus $from, ?string $note, ?string $jabatan = null): void
    {
        $document->logs()->create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'jabatan' => $jabatan ?? $user->employee?->position ?? $user->position,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $document->status->value,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
