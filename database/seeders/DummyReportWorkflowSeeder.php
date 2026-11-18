<?php

namespace Database\Seeders;

use App\Enums\EmployeePosition;
use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Models\Employee;
use App\Models\HarDocumentRecord;
use App\Models\K3DocumentRecord;
use App\Models\LogistikDocumentRecord;
use App\Models\OperasiReportDocument;
use App\Models\PdmDocumentRecord;
use App\Models\ReportPeriod;
use App\Models\ReportWorkflow;
use App\Models\ReportWorkflowLog;
use App\Models\ReportWorkflowStep;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\ReportSignatories;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds saved report documents and full verification/approval workflows
 * (report_workflows, report_workflow_steps, report_workflow_logs) for all 8
 * ReportModule cases across all 22 active units:
 * - Previous month (Oct 2026): 100% Final with complete audit trail & signatures.
 * - Current month (Nov 2026): Realistic distribution of Final, Disetujui (awaiting
 *   Manager UL), Verifikasi (awaiting Team Leader), Diajukan (awaiting Koordinator),
 *   and Ditolak (sample rejection) so ApprovalInbox, ManagerUlHome, and
 *   ReportMonitoring are rich and interactive.
 */
class DummyReportWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $year = (int) $now->year;
        $curMonth = (int) $now->month;
        $prevMonth = $curMonth === 1 ? 12 : $curMonth - 1;
        $prevYear = $curMonth === 1 ? $year - 1 : $year;

        $units = Unit::query()->where('is_active', true)->orderBy('id')->get();
        $signatories = app(ReportSignatories::class);
        $fallbackUser = User::query()->first();

        foreach ($units as $uIdx => $unit) {
            foreach ([[$prevYear, $prevMonth, true], [$year, $curMonth, false]] as [$mYear, $mMonth, $isPrevMonth]) {
                $this->seedSavedDocuments($unit, $mYear, $mMonth, $fallbackUser?->id);

                foreach (ReportModule::cases() as $modIdx => $module) {
                    $targetStatus = $isPrevMonth
                        ? ReportStatus::Final
                        : $this->targetStatusForCurrentMonth($unit->id, $uIdx, $module, $modIdx);

                    $this->seedWorkflowForModule($unit, $module, $mYear, $mMonth, $targetStatus, $signatories, $fallbackUser);
                }
            }
        }
    }

    private function targetStatusForCurrentMonth(int $unitId, int $uIdx, ReportModule $module, int $modIdx): ReportStatus
    {
        // Specifically curate PLTD Poasia (#1) and PLTD Poasia Containerized (#2)
        if ($unitId === 1 || $unitId === 2) {
            return match ($module) {
                ReportModule::Operasi => ReportStatus::Final,
                ReportModule::Har => ReportStatus::Disetujui,
                ReportModule::K3 => ReportStatus::Verifikasi,
                ReportModule::Logistik => ReportStatus::Verifikasi,
                ReportModule::Pdm => ReportStatus::Diajukan,
                ReportModule::OperasiPengusahaan => ReportStatus::Verifikasi,
                ReportModule::HarPengusahaan => ReportStatus::Disetujui,
                ReportModule::K3Pengusahaan => ReportStatus::Final,
            };
        }

        // Sample rejection on one unit/module so ReportMonitoring shows recent rejections
        if ($uIdx === 4 && $module === ReportModule::Logistik) {
            return ReportStatus::Ditolak;
        }

        return match (($uIdx + $modIdx) % 4) {
            0 => ReportStatus::Final,
            1 => ReportStatus::Disetujui,
            2 => ReportStatus::Verifikasi,
            default => ReportStatus::Diajukan,
        };
    }

    private function seedSavedDocuments(Unit $unit, int $year, int $month, ?int $userId): void
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $period = ReportPeriod::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            ['total_days' => $daysInMonth, 'total_hours' => $daysInMonth * 24],
        );

        $docHtml = fn (string $title): string => "<div class=\"report-doc\"><h2>{$title} — {$unit->name}</h2><p>Periode: ".sprintf('%02d/%d', $month, $year).'</p><p>Seluruh data operasional, pemeliharaan, K3, logistik, dan PdM telah direkapitulasi sesuai realisasi unit.</p></div>';

        // 1. Operasi (bulanan & pengusahaan)
        foreach (['laporan-operasi-bulanan' => 'LAPORAN OPERASI PEMBANGKIT', 'pengusahaan' => 'LAPORAN OPERASI'] as $code => $title) {
            OperasiReportDocument::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'report_code' => $code, 'engine_id' => null, 'month' => $month, 'year' => $year],
                [
                    'document_number' => sprintf('DOC-OPS-%s/%s/%02d/%d', strtoupper($code === 'pengusahaan' ? 'PNG' : 'BLN'), $unit->code, $month, $year),
                    'format' => 'html',
                    'content_version' => '1.0',
                    'content_html' => $docHtml($title),
                    'content_grid' => [],
                    'snapshot' => ['generated_at' => now()->toIso8601String()],
                    'created_by' => $userId,
                ],
            );
        }

        // 2. HAR (bulanan & pengusahaan)
        foreach (['bulanan' => 'LAPORAN PEMELIHARAAN PEMBANGKIT', 'pengusahaan' => 'LAPORAN PEMELIHARAAN'] as $type => $title) {
            HarDocumentRecord::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'type' => $type, 'month' => $month, 'year' => $year],
                [
                    'report_period_id' => $period->id,
                    'document_number' => sprintf('DOC-HAR-%s/%s/%02d/%d', strtoupper($type === 'pengusahaan' ? 'PNG' : 'BLN'), $unit->code, $month, $year),
                    'format' => 'html',
                    'content_version' => '1.0',
                    'content_html' => $docHtml($title),
                    'content_grid' => [],
                    'snapshot' => ['generated_at' => now()->toIso8601String()],
                    'created_by' => $userId,
                ],
            );
        }

        // 3. K3 (bulanan & pengusahaan)
        foreach (['bulanan' => 'LAPORAN K3 & KEAMANAN PEMBANGKIT', 'pengusahaan' => 'LAPORAN K3 & KEAMANAN'] as $type => $title) {
            K3DocumentRecord::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'type' => $type, 'month' => $month, 'year' => $year],
                [
                    'document_number' => sprintf('DOC-K3-%s/%s/%02d/%d', strtoupper($type === 'pengusahaan' ? 'PNG' : 'BLN'), $unit->code, $month, $year),
                    'format' => 'html',
                    'content_version' => '1.0',
                    'content_html' => $docHtml($title),
                    'content_grid' => [],
                    'snapshot' => ['generated_at' => now()->toIso8601String()],
                    'created_by' => $userId,
                ],
            );
        }

        // 4. Logistik (bulanan)
        LogistikDocumentRecord::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'type' => 'bulanan', 'month' => $month, 'year' => $year],
            [
                'document_number' => sprintf('DOC-LOG-BLN/%s/%02d/%d', $unit->code, $month, $year),
                'format' => 'html',
                'content_version' => '1.0',
                'content_html' => $docHtml('LAPORAN LOGISTIK & GUDANG'),
                'content_grid' => [],
                'snapshot' => ['generated_at' => now()->toIso8601String()],
                'created_by' => $userId,
            ],
        );

        // 5. PdM (bulanan)
        PdmDocumentRecord::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'type' => 'bulanan', 'month' => $month, 'year' => $year],
            [
                'document_number' => sprintf('DOC-PDM-BLN/%s/%02d/%d', $unit->code, $month, $year),
                'format' => 'html',
                'content_version' => '1.0',
                'content_html' => $docHtml('LAPORAN PREDICTIVE MAINTENANCE (PdM)'),
                'content_grid' => [],
                'snapshot' => ['generated_at' => now()->toIso8601String()],
                'created_by' => $userId,
            ],
        );
    }

    private function seedWorkflowForModule(
        Unit $unit,
        ReportModule $module,
        int $year,
        int $month,
        ReportStatus $targetStatus,
        ReportSignatories $signatories,
        ?User $fallbackUser,
    ): void {
        ReportWorkflow::query()
            ->where('unit_id', $unit->id)
            ->where('module', $module->value)
            ->where('year', $year)
            ->where('month', $month)
            ->delete();

        $isPengusahaan = $module->isPengusahaan();

        // Resolve submitter (Office / PIC PDM for Akses 1, Staf / TL for Akses 2)
        $submitterEmp = null;
        if ($isPengusahaan) {
            $stafPos = match ($module) {
                ReportModule::OperasiPengusahaan => 'Staf Operasi',
                ReportModule::HarPengusahaan => 'Staf Pemeliharaan',
                ReportModule::K3Pengusahaan => 'Staf K3',
                default => 'Staf Operasi',
            };
            $submitterEmp = Employee::query()->where('unit_id', $unit->id)->where('position', $stafPos)->first()
                ?? $signatories->holder($unit, $module->teamLeaderPosition());
        } else {
            $officePos = $module === ReportModule::Pdm
                ? EmployeePosition::PicPdm
                : EmployeePosition::officeFor($module->division());
            $submitterEmp = $signatories->holder($unit, $officePos);
        }

        $submitterUserId = $submitterEmp?->user?->id ?? $fallbackUser?->id;
        $submitterName = $submitterEmp?->name ?? 'Petugas Unit';
        $submitterJabatan = $submitterEmp?->position ?? 'Office';

        $baseTime = Carbon::create($year, $month, min(Carbon::create($year, $month, 1)->daysInMonth, 10), 9, 0, 0);
        if ($baseTime->isFuture()) {
            $baseTime = Carbon::now()->subDays(2)->setTime(9, 0);
        }
        $tSubmit = $baseTime->copy();
        $tVerify = $baseTime->copy()->addHours(4);
        $tApprove = $baseTime->copy()->addHours(10);
        $tFinal = $baseTime->copy()->addHours(18);

        // Pengusahaan starts at Verifikasi when submitted (since it has no Koordinator step 1)
        $effectiveStatus = ($isPengusahaan && $targetStatus === ReportStatus::Diajukan)
            ? ReportStatus::Verifikasi
            : $targetStatus;

        $koordEmp = $isPengusahaan ? null : $signatories->holder($unit, EmployeePosition::koordinatorFor($module->division()));
        $tlEmp = $signatories->holder($unit, $module->teamLeaderPosition());
        $mgrEmp = $signatories->holder($unit, EmployeePosition::ManagerUl);

        $verifiedBy = null;
        $verifiedAt = null;
        $verificationNote = null;
        $rejectedBy = null;
        $rejectedAt = null;
        $rejectionReason = null;
        $finalizedAt = null;

        if (! $isPengusahaan && in_array($effectiveStatus, [ReportStatus::Verifikasi, ReportStatus::Disetujui, ReportStatus::Final], true)) {
            $verifiedBy = $koordEmp?->user?->id ?? $fallbackUser?->id;
            $verifiedAt = $tVerify;
            $verificationNote = 'Data laporan telah diperiksa dan sesuai logsheet harian.';
        }

        if ($effectiveStatus === ReportStatus::Final) {
            $finalizedAt = $tFinal;
        }

        if ($effectiveStatus === ReportStatus::Ditolak) {
            $rejectedBy = $koordEmp?->user?->id ?? $tlEmp?->user?->id ?? $fallbackUser?->id;
            $rejectedAt = $tVerify;
            $rejectionReason = 'Mohon lengkapi lampiran eviden foto kegiatan sebelum diajukan kembali.';
        }

        $workflow = ReportWorkflow::query()->create([
            'module' => $module,
            'unit_id' => $unit->id,
            'month' => $month,
            'year' => $year,
            'status' => $effectiveStatus,
            'submitted_by' => $submitterUserId,
            'submitted_at' => $tSubmit,
            'verified_by' => $verifiedBy,
            'verified_at' => $verifiedAt,
            'verification_note' => $verificationNote,
            'rejected_by' => $rejectedBy,
            'rejected_at' => $rejectedAt,
            'rejection_reason' => $rejectionReason,
            'finalized_at' => $finalizedAt,
        ]);

        // Create pengesahan steps
        foreach ($module->pengesahanSigners() as $spec) {
            $seq = $spec['sequence'];
            $pos = $spec['position'];
            $emp = $signatories->holder($unit, $pos);
            $empUserId = $emp?->user?->id ?? $fallbackUser?->id;

            $isSigned = match ($seq) {
                1 => in_array($effectiveStatus, [ReportStatus::Verifikasi, ReportStatus::Disetujui, ReportStatus::Final], true),
                2 => in_array($effectiveStatus, [ReportStatus::Disetujui, ReportStatus::Final], true),
                3 => $effectiveStatus === ReportStatus::Final,
                default => false,
            };

            $stepSignedAt = match ($seq) {
                1 => $tVerify,
                2 => $tApprove,
                3 => $tFinal,
                default => $tFinal,
            };

            $stepNote = match ($seq) {
                1 => 'Telah diperiksa oleh Koordinator.',
                2 => 'Telah disetujui oleh Team Leader.',
                3 => 'Telah disahkan oleh Manager UL.',
                default => null,
            };

            ReportWorkflowStep::query()->create([
                'report_workflow_id' => $workflow->id,
                'stage' => ReportWorkflowStep::STAGE_PENGESAHAN,
                'sequence' => $seq,
                'caption' => $spec['caption'],
                'position' => $pos->value,
                'employee_id' => $emp?->id,
                'signed_by' => $isSigned ? $empUserId : null,
                'signed_at' => $isSigned ? $stepSignedAt : null,
                'note' => $isSigned ? $stepNote : null,
            ]);
        }

        // Create in-report tanda_tangan steps
        foreach ($module->reportSigners() as $idx => $spec) {
            $pos = $spec['position'];
            $emp = $signatories->holder($unit, $pos);
            ReportWorkflowStep::query()->create([
                'report_workflow_id' => $workflow->id,
                'stage' => ReportWorkflowStep::STAGE_TANDA_TANGAN,
                'sequence' => $idx + 1,
                'caption' => $spec['caption'],
                'position' => $pos->value,
                'employee_id' => $emp?->id,
                'signed_by' => $effectiveStatus === ReportStatus::Final ? ($emp?->user?->id ?? $fallbackUser?->id) : null,
                'signed_at' => $effectiveStatus === ReportStatus::Final ? $tFinal : null,
                'note' => null,
            ]);
        }

        // Create workflow logs
        $firstStatus = $isPengusahaan ? ReportStatus::Verifikasi->value : ReportStatus::Diajukan->value;
        ReportWorkflowLog::query()->create([
            'report_workflow_id' => $workflow->id,
            'user_id' => $submitterUserId,
            'user_name' => $submitterName,
            'jabatan' => $submitterJabatan,
            'action' => 'ajukan',
            'from_status' => ReportStatus::Draft->value,
            'to_status' => $firstStatus,
            'note' => 'Laporan diajukan untuk proses pemeriksaan dan pengesahan.',
            'created_at' => $tSubmit,
        ]);

        if (! $isPengusahaan && in_array($effectiveStatus, [ReportStatus::Verifikasi, ReportStatus::Disetujui, ReportStatus::Final], true)) {
            ReportWorkflowLog::query()->create([
                'report_workflow_id' => $workflow->id,
                'user_id' => $koordEmp?->user?->id ?? $fallbackUser?->id,
                'user_name' => $koordEmp?->name ?? 'Koordinator',
                'jabatan' => EmployeePosition::koordinatorFor($module->division())->value,
                'action' => 'verifikasi',
                'from_status' => ReportStatus::Diajukan->value,
                'to_status' => ReportStatus::Verifikasi->value,
                'note' => 'Data lengkap dan telah diperiksa.',
                'created_at' => $tVerify,
            ]);
        }

        if (in_array($effectiveStatus, [ReportStatus::Disetujui, ReportStatus::Final], true)) {
            ReportWorkflowLog::query()->create([
                'report_workflow_id' => $workflow->id,
                'user_id' => $tlEmp?->user?->id ?? $fallbackUser?->id,
                'user_name' => $tlEmp?->name ?? 'Team Leader',
                'jabatan' => $module->teamLeaderPosition()->value,
                'action' => 'setujui',
                'from_status' => ReportStatus::Verifikasi->value,
                'to_status' => ReportStatus::Disetujui->value,
                'note' => 'Disetujui oleh Team Leader.',
                'created_at' => $tApprove,
            ]);
        }

        if ($effectiveStatus === ReportStatus::Final) {
            ReportWorkflowLog::query()->create([
                'report_workflow_id' => $workflow->id,
                'user_id' => $mgrEmp?->user?->id ?? $fallbackUser?->id,
                'user_name' => $mgrEmp?->name ?? 'Manager UL',
                'jabatan' => EmployeePosition::ManagerUl->value,
                'action' => 'sahkan',
                'from_status' => ReportStatus::Disetujui->value,
                'to_status' => ReportStatus::Final->value,
                'note' => 'Laporan disahkan dan berstatus Final.',
                'created_at' => $tFinal,
            ]);
        }

        if ($effectiveStatus === ReportStatus::Ditolak) {
            ReportWorkflowLog::query()->create([
                'report_workflow_id' => $workflow->id,
                'user_id' => $rejectedBy,
                'user_name' => $koordEmp?->name ?? 'Koordinator',
                'jabatan' => EmployeePosition::koordinatorFor($module->division())->value,
                'action' => 'tolak',
                'from_status' => ReportStatus::Diajukan->value,
                'to_status' => ReportStatus::Ditolak->value,
                'note' => $rejectionReason,
                'created_at' => $tVerify,
            ]);
        }
    }
}
