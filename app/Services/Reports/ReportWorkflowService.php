<?php

namespace App\Services\Reports;

use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Models\Employee;
use App\Models\ReportWorkflow;
use App\Models\ReportWorkflowStep;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

/**
 * The verification & pengesahan workflow of the Laporan Pembangkit:
 *
 *   DRAFT ─ajukan→ DIAJUKAN ─verifikasi→ VERIFIKASI ─pengesahan (Koordinator
 *   Pemeliharaan → TL Pemeliharaan → Manager UL)→ DISETUJUI … DISAHKAN
 *   ─tanda tangan (Project Leader + Office divisi; PdM: Koordinator
 *   Pemeliharaan + PIC PDM)→ DITANDATANGANI … FINAL
 *
 * A verifier or the signer whose turn it is may reject (DITOLAK); the report
 * is then editable again and must be diajukan kembali, which re-freezes the
 * signers. Every transition is authorised here (permission + unit scope for
 * ajukan / verifikasi; the signer's own linked account for each signature),
 * never by role name, and written to the audit trail.
 */
class ReportWorkflowService
{
    public function __construct(private readonly ReportSignatories $signatories) {}

    public function find(ReportModule $module, int $unitId, int $month, int $year): ?ReportWorkflow
    {
        return ReportWorkflow::query()
            ->where('module', $module)->where('unit_id', $unitId)
            ->where('month', $month)->where('year', $year)
            ->with(['steps.employee', 'steps.signer', 'logs', 'submitter', 'verifier', 'rejecter'])
            ->first();
    }

    public function status(?ReportWorkflow $workflow): ReportStatus
    {
        return $workflow?->status ?? ReportStatus::Draft;
    }

    /**
     * Only a draft or rejected report may be edited, saved or regenerated.
     */
    public function isEditable(ReportModule $module, int $unitId, int $month, int $year): bool
    {
        return $this->status($this->find($module, $unitId, $month, $year))->isEditable();
    }

    public function canSubmit(User $user, ReportModule $module, Unit $unit, ?ReportWorkflow $workflow): bool
    {
        return $this->status($workflow)->canBeSubmitted()
            && $user->hasPermissionTo($module->writePermission())
            && $user->canAccessUnit($unit);
    }

    public function canVerify(User $user, ?ReportWorkflow $workflow): bool
    {
        return $workflow !== null
            && $workflow->status === ReportStatus::Diajukan
            && $user->hasPermissionTo(PermissionName::ReportUnitApprove)
            && $user->canAccessUnit($workflow->unit_id)
            && $workflow->submitted_by !== $user->id;
    }

    /**
     * The step the user may sign now: the first unsigned step, in its stage's
     * turn, assigned to the employee linked to the user's own account.
     */
    public function signableStep(User $user, ?ReportWorkflow $workflow): ?ReportWorkflowStep
    {
        $step = $workflow?->currentStep();
        if ($step === null || $step->employee_id === null) {
            return null;
        }

        $stageOpen = $step->isPengesahan()
            ? in_array($workflow->status, [ReportStatus::Verifikasi, ReportStatus::Disetujui], true)
            : in_array($workflow->status, [ReportStatus::Disahkan, ReportStatus::Ditandatangani], true);

        $employee = $this->employeeOf($user);

        return $stageOpen && $employee !== null && $employee->is_active && $employee->id === $step->employee_id ? $step : null;
    }

    public function canReject(User $user, ?ReportWorkflow $workflow): bool
    {
        return $this->canVerify($user, $workflow) || $this->signableStep($user, $workflow) !== null;
    }

    /**
     * Ajukan: freeze the signers of the unit and send the saved report to
     * verification.
     */
    public function submit(User $user, ReportModule $module, Unit $unit, int $month, int $year, ?string $note = null): ReportWorkflow
    {
        $workflow = $this->find($module, $unit->id, $month, $year);
        abort_unless($this->canSubmit($user, $module, $unit, $workflow), 403);

        if (! $module->hasSavedDocument($unit->id, $month, $year)) {
            throw ValidationException::withMessages(['workflow' => 'Simpan dokumen laporan terlebih dahulu sebelum diajukan.']);
        }

        $signers = $this->resolveSigners($module, $unit);
        $missing = collect($signers)->filter(fn (array $signer): bool => $signer['employee'] === null)
            ->map(fn (array $signer): string => $signer['position']->value)->unique()->values();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'workflow' => "Pegawai aktif dengan jabatan {$missing->join(', ', ' & ')} di {$unit->name} belum terdaftar di Master Pegawai.",
            ]);
        }

        return DB::transaction(function () use ($user, $module, $unit, $month, $year, $note, $workflow, $signers): ReportWorkflow {
            $workflow ??= new ReportWorkflow(['module' => $module, 'unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'status' => ReportStatus::Draft]);
            $from = $workflow->exists ? $workflow->status : ReportStatus::Draft;
            $resubmitted = $from === ReportStatus::Ditolak;

            $workflow->fill([
                'status' => ReportStatus::Diajukan,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'verified_by' => null,
                'verified_at' => null,
                'verification_note' => null,
                'finalized_at' => null,
            ])->save();

            $workflow->steps()->delete();
            foreach ($signers as $signer) {
                $workflow->steps()->create([
                    'stage' => $signer['stage'],
                    'sequence' => $signer['sequence'],
                    'caption' => $signer['caption'],
                    'position' => $signer['position']->value,
                    'employee_id' => $signer['employee']->id,
                ]);
            }

            $this->log($workflow, $user, $resubmitted ? 'ajukan_kembali' : 'ajukan', $from, $note);

            return $workflow->fresh();
        });
    }

    public function verify(User $user, ReportWorkflow $workflow, ?string $note = null): ReportWorkflow
    {
        abort_unless($this->canVerify($user, $workflow), 403);

        return DB::transaction(function () use ($user, $workflow, $note): ReportWorkflow {
            $from = $workflow->status;
            $workflow->fill([
                'status' => ReportStatus::Verifikasi,
                'verified_by' => $user->id,
                'verified_at' => now(),
                'verification_note' => $note,
            ])->save();

            $this->log($workflow, $user, 'verifikasi', $from, $note);

            return $workflow->fresh();
        });
    }

    public function reject(User $user, ReportWorkflow $workflow, string $reason): ReportWorkflow
    {
        abort_unless($this->canReject($user, $workflow), 403);

        return DB::transaction(function () use ($user, $workflow, $reason): ReportWorkflow {
            $from = $workflow->status;
            $workflow->fill([
                'status' => ReportStatus::Ditolak,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();
            $workflow->steps()->update(['signed_by' => null, 'signed_at' => null, 'note' => null]);

            $this->log($workflow, $user, 'tolak', $from, $reason);

            return $workflow->fresh();
        });
    }

    /**
     * Setujui / Sahkan (Lembar Pengesahan) or Tanda Tangani (in-report block):
     * signs the user's step and advances the status; the last signature makes
     * the report FINAL.
     */
    public function sign(User $user, ReportWorkflow $workflow, ?string $note = null): ReportWorkflow
    {
        $step = $this->signableStep($user, $workflow);
        abort_if($step === null, 403);

        return DB::transaction(function () use ($user, $workflow, $step, $note): ReportWorkflow {
            $from = $workflow->status;
            $step->fill(['signed_by' => $user->id, 'signed_at' => now(), 'note' => $note])->save();

            $remaining = $workflow->steps()->whereNull('signed_at')->get();
            $pengesahanLeft = $remaining->contains(fn (ReportWorkflowStep $s): bool => $s->isPengesahan());

            $to = match (true) {
                $remaining->isEmpty() => ReportStatus::Final,
                $step->isPengesahan() => $pengesahanLeft ? ReportStatus::Disetujui : ReportStatus::Disahkan,
                default => ReportStatus::Ditandatangani,
            };

            $workflow->fill(['status' => $to, 'finalized_at' => $to === ReportStatus::Final ? now() : null])->save();

            $this->log($workflow, $user, $this->signAction($workflow, $step), $from, $note, $step->position);

            return $workflow->fresh();
        });
    }

    /**
     * The workflow as shown by the document page: status, verification and
     * rejection details, every signer with Sudah / Belum, the audit trail and
     * which actions the current user may take (computed here, so the page
     * shows only the buttons the backend will accept).
     *
     * @return array<string, mixed>
     */
    public function present(User $user, ReportModule $module, Unit $unit, int $month, int $year): array
    {
        $workflow = $this->find($module, $unit->id, $month, $year);
        $status = $this->status($workflow);
        $signable = $this->signableStep($user, $workflow);
        $userName = fn (?User $u): ?string => $u?->name;

        return [
            'module' => $module->value,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_tone' => $status->tone(),
            'editable' => $status->isEditable(),
            'submitted' => $workflow?->submitted_at ? ['by' => $userName($workflow->submitter), 'at' => $workflow->submitted_at->toIso8601String()] : null,
            'verification' => $workflow?->verified_at ? [
                'by' => $userName($workflow->verifier),
                'at' => $workflow->verified_at->toIso8601String(),
                'note' => $workflow->verification_note,
            ] : null,
            'rejection' => $workflow?->rejected_at ? [
                'by' => $userName($workflow->rejecter),
                'at' => $workflow->rejected_at->toIso8601String(),
                'reason' => $workflow->rejection_reason,
                'active' => $status === ReportStatus::Ditolak,
            ] : null,
            'steps' => $this->signerRows($module, $unit, $workflow),
            'logs' => $workflow?->logs->map(fn ($log): array => [
                'id' => $log->id,
                'user' => $log->user_name,
                'jabatan' => $log->jabatan,
                'action' => $log->action,
                'from' => $log->from_status ? ReportStatus::from($log->from_status)->label() : null,
                'to' => ReportStatus::from($log->to_status)->label(),
                'note' => $log->note,
                'at' => $log->created_at?->toIso8601String(),
            ])->reverse()->values()->all() ?? [],
            'can' => [
                'submit' => $this->canSubmit($user, $module, $unit, $workflow),
                'verify' => $this->canVerify($user, $workflow),
                'reject' => $this->canReject($user, $workflow),
                'sign' => $signable !== null ? $this->signLabel($workflow, $signable) : null,
            ],
        ];
    }

    /**
     * The Lembar Pengesahan and the in-report signature blocks as HTML tables
     * with a fixed id, so they can be refreshed inside a saved document.
     *
     * @return array{pengesahan: string, laporan: string}
     */
    public function signatureBlocks(ReportModule $module, Unit $unit, int $month, int $year): array
    {
        $workflow = $this->find($module, $unit->id, $month, $year);
        $rows = collect($this->signerRows($module, $unit, $workflow));
        $final = $this->status($workflow) === ReportStatus::Final;
        $cells = fn (string $stage): array => $rows->where('stage', $stage)->map(fn (array $row): array => [
            'caption' => $row['caption'],
            'position' => $row['position'],
            'name' => $row['name'],
            'image' => $final ? $row['image'] : null,
            'signed_at' => $final ? $row['signed_at'] : null,
        ])->values()->all();

        return [
            // Printed Mengetahui · Menyetujui · Memeriksa (reverse signing order).
            'pengesahan' => View::make('reports.partials.signature-block', ['id' => 'ttd-pengesahan', 'signers' => array_reverse($cells(ReportWorkflowStep::STAGE_PENGESAHAN))])->render(),
            'laporan' => View::make('reports.partials.signature-block', ['id' => 'ttd-laporan', 'signers' => $cells(ReportWorkflowStep::STAGE_TANDA_TANGAN)])->render(),
        ];
    }

    /**
     * Replace the signature blocks inside a (saved) document body with the
     * current ones, so an edited document always shows the right signers and
     * prints signatures only once the report is FINAL.
     */
    public function refreshSignatureBlocks(string $html, ReportModule $module, Unit $unit, int $month, int $year): string
    {
        if (! str_contains($html, 'ttd-pengesahan') && ! str_contains($html, 'ttd-laporan')) {
            return $html;
        }

        $blocks = $this->signatureBlocks($module, $unit, $month, $year);

        foreach (['ttd-pengesahan' => $blocks['pengesahan'], 'ttd-laporan' => $blocks['laporan']] as $id => $block) {
            $html = (string) preg_replace_callback(
                '/<table\b[^>]*\bid=["\']'.$id.'["\'][^>]*>.*?<\/table>/is',
                fn (): string => $block,
                $html,
            );
        }

        return $html;
    }

    /**
     * Whether the user is one of the frozen signers of the report — a signer
     * may open the document to read what they sign.
     */
    public function isSigner(User $user, ReportModule $module, int $unitId, int $month, int $year): bool
    {
        $employee = $this->employeeOf($user);
        if ($employee === null) {
            return false;
        }

        return ReportWorkflowStep::query()
            ->where('employee_id', $employee->id)
            ->whereHas('workflow', fn ($q) => $q->where('module', $module)->where('unit_id', $unitId)->where('month', $month)->where('year', $year))
            ->exists();
    }

    /**
     * @return list<array{stage: string, sequence: int, caption: string, position: EmployeePosition, employee: Employee|null}>
     */
    private function resolveSigners(ReportModule $module, Unit $unit): array
    {
        $signers = [];
        foreach ([ReportWorkflowStep::STAGE_PENGESAHAN => $module->pengesahanSigners(), ReportWorkflowStep::STAGE_TANDA_TANGAN => $module->reportSigners()] as $stage => $list) {
            foreach ($list as $index => $signer) {
                $signers[] = [
                    'stage' => $stage,
                    'sequence' => $index + 1,
                    'caption' => $signer['caption'],
                    'position' => $signer['position'],
                    'employee' => $this->signatories->holder($unit, $signer['position']),
                ];
            }
        }

        return $signers;
    }

    /**
     * Every signer in workflow order: the frozen step once the report has been
     * diajukan, otherwise the current holder of the jabatan in the unit.
     *
     * @return list<array{stage: string, sequence: int, caption: string, position: string, name: string|null, employee_id: int|null, signed: bool, signed_at: string|null, signed_by: string|null, image: string|null, current: bool}>
     */
    private function signerRows(ReportModule $module, Unit $unit, ?ReportWorkflow $workflow): array
    {
        if ($workflow !== null && $workflow->steps->isNotEmpty()) {
            $current = $workflow->currentStep();

            return $workflow->steps->map(fn (ReportWorkflowStep $step): array => [
                'stage' => $step->stage,
                'sequence' => $step->sequence,
                'caption' => $step->caption,
                'position' => $step->position,
                'name' => $step->employee?->name,
                'employee_id' => $step->employee_id,
                'signed' => $step->signed_at !== null,
                'signed_at' => $step->signed_at?->toIso8601String(),
                'signed_by' => $step->signer?->name,
                'image' => $this->signatories->signatureImage($step->employee),
                'current' => $current?->is($step) === true && ! in_array($workflow->status, [ReportStatus::Diajukan, ReportStatus::Ditolak], true),
            ])->values()->all();
        }

        return array_map(fn (array $signer): array => [
            'stage' => $signer['stage'],
            'sequence' => $signer['sequence'],
            'caption' => $signer['caption'],
            'position' => $signer['position']->value,
            'name' => $signer['employee']?->name,
            'employee_id' => $signer['employee']?->id,
            'signed' => false,
            'signed_at' => null,
            'signed_by' => null,
            'image' => null,
            'current' => false,
        ], $this->resolveSigners($module, $unit));
    }

    private function employeeOf(User $user): ?Employee
    {
        return $user->relationLoaded('employee') ? $user->employee : $user->load('employee')->employee;
    }

    private function isLastPengesahan(ReportWorkflow $workflow, ReportWorkflowStep $step): bool
    {
        return $step->isPengesahan()
            && $step->sequence === $workflow->steps->where('stage', ReportWorkflowStep::STAGE_PENGESAHAN)->max('sequence');
    }

    private function signLabel(ReportWorkflow $workflow, ReportWorkflowStep $step): string
    {
        return match (true) {
            ! $step->isPengesahan() => 'Tanda Tangani',
            $this->isLastPengesahan($workflow, $step) => 'Sahkan',
            default => 'Setujui',
        };
    }

    private function signAction(ReportWorkflow $workflow, ReportWorkflowStep $step): string
    {
        return match ($this->signLabel($workflow, $step)) {
            'Sahkan' => 'sahkan',
            'Setujui' => 'setujui',
            default => 'tanda_tangan',
        };
    }

    private function log(ReportWorkflow $workflow, User $user, string $action, ?ReportStatus $from, ?string $note, ?string $jabatan = null): void
    {
        $workflow->logs()->create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'jabatan' => $jabatan ?? $this->employeeOf($user)?->position ?? $user->position,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $workflow->status->value,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
