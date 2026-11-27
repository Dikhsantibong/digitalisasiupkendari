<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Models\OperatorMutasi;
use App\Models\ReportWorkflow;
use App\Models\ReportWorkflowStep;
use App\Services\Reports\SignatureQrService;
use App\Support\Indonesian;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * Public QR signature verification endpoint. No login required so scanning a
 * printed or exported PDF signature immediately confirms document authenticity.
 */
class SignatureVerificationController extends Controller
{
    public function show(string $ref, string $token, SignatureQrService $qr): View
    {
        if (str_starts_with($ref, 'ms')) {
            return $this->verifyMutasi((int) substr($ref, 2), 'penyerah', $ref, $token, $qr);
        }

        if (str_starts_with($ref, 'mt')) {
            return $this->verifyMutasi((int) substr($ref, 2), 'penerima', $ref, $token, $qr);
        }

        if (str_starts_with($ref, 'w')) {
            return $this->verifySubmission((int) substr($ref, 1), $ref, $token, $qr);
        }

        return $this->verifyStep((int) $ref, $ref, $token, $qr);
    }

    private function verifyMutasi(int $mutasiId, string $role, string $ref, string $token, SignatureQrService $qr): View
    {
        $mutasi = OperatorMutasi::query()->with('unit')->find($mutasiId);

        if ($mutasi === null || ! $qr->verifyToken($ref, $mutasi->id, $token)) {
            return view('reports.signature-verify', $this->invalidPayload());
        }

        $isPenerima = $role === 'penerima';
        $signedAt = $isPenerima ? $mutasi->diterima_at : $mutasi->diserahkan_at;
        $valid = $signedAt !== null;

        $tanggal = Carbon::parse($mutasi->tanggal->toDateString());
        $shiftLabel = ucfirst($mutasi->shift);
        $shiftHours = OperatorMutasi::SHIFTS[$mutasi->shift] ?? '';
        $reguPenyerah = $mutasi->regu_penyerah ?: '—';
        $reguPenerima = $mutasi->regu_penerima ?: '—';

        $chain = [
            [
                'caption' => 'Menyerahkan',
                'position' => "Leader Shift (Regu {$reguPenyerah})",
                'name' => $mutasi->penyerah_nama ?: '—',
                'signed' => $mutasi->diserahkan_at !== null,
                'signed_at' => $mutasi->diserahkan_at?->timezone(config('app.timezone'))->format('d M Y, H:i'),
            ],
            [
                'caption' => 'Menerima',
                'position' => "Leader Shift (Regu {$reguPenerima})",
                'name' => $mutasi->penerima_nama ?: '—',
                'signed' => $mutasi->diterima_at !== null,
                'signed_at' => $mutasi->diterima_at?->timezone(config('app.timezone'))->format('d M Y, H:i'),
            ],
        ];

        $workflowStatus = match (true) {
            $mutasi->diterima_at !== null => 'Diterima & Terkunci',
            $mutasi->diserahkan_at !== null => 'Diserahkan — Menunggu Penerima',
            default => 'Draft',
        };

        return view('reports.signature-verify', [
            'found' => true,
            'valid' => $valid,
            'statusBadge' => $valid ? 'SAH & TERVERIFIKASI' : 'BELUM DISAHKAN',
            'reason' => $valid
                ? 'Tanda tangan elektronik serah terima shift tercatat sah di sistem KIT-SIPP.'
                : 'Tahap tanda tangan serah terima shift ini belum diselesaikan.',
            'documentTitle' => 'Lembar Mutasi Operator',
            'unitName' => $mutasi->unit?->name ?? '—',
            'period' => Indonesian::dayName($tanggal).', '.Indonesian::longDate($tanggal)." · Shift {$shiftLabel}".($shiftHours ? " ({$shiftHours})" : ''),
            'workflowStatus' => $workflowStatus,
            'signerName' => $isPenerima ? ($mutasi->penerima_nama ?: '—') : ($mutasi->penyerah_nama ?: '—'),
            'signerPosition' => 'Operator / Leader Shift (Regu '.($isPenerima ? $reguPenerima : $reguPenyerah).')',
            'signerRole' => $isPenerima ? 'Regu Penerima' : 'Regu Penyerah',
            'signedAt' => $signedAt?->timezone(config('app.timezone'))->format('d M Y, H:i \W\I\T\A'),
            'note' => null,
            'chain' => $chain,
        ]);
    }

    private function verifyStep(int $stepId, string $ref, string $token, SignatureQrService $qr): View
    {
        $step = ReportWorkflowStep::query()
            ->with(['workflow.unit', 'workflow.steps.employee', 'workflow.steps.signer', 'employee', 'signer'])
            ->find($stepId);

        if ($step === null || $step->workflow === null || ! $qr->verifyToken($ref, $step->report_workflow_id, $token)) {
            return view('reports.signature-verify', $this->invalidPayload());
        }

        $workflow = $step->workflow;
        $status = $workflow->status;
        $isPengesahan = $step->stage === ReportWorkflowStep::STAGE_PENGESAHAN;
        $signedAt = $isPengesahan
            ? $step->signed_at
            : ($status === ReportStatus::Final ? ($step->signed_at ?? $workflow->finalized_at) : null);

        $valid = $status !== ReportStatus::Ditolak
            && ($isPengesahan ? $step->signed_at !== null : $status === ReportStatus::Final && $signedAt !== null);

        return view('reports.signature-verify', $this->buildPayload(
            workflow: $workflow,
            valid: $valid,
            signerName: $step->employee?->name ?? $step->signer?->name ?? '—',
            signerPosition: $step->position,
            signerRole: $step->caption,
            signedAt: $signedAt,
            note: $step->note,
        ));
    }

    private function verifySubmission(int $workflowId, string $ref, string $token, SignatureQrService $qr): View
    {
        $workflow = ReportWorkflow::query()
            ->with(['unit', 'submitter.employee', 'steps.employee', 'steps.signer'])
            ->find($workflowId);

        if ($workflow === null || ! $qr->verifyToken($ref, $workflow->id, $token)) {
            return view('reports.signature-verify', $this->invalidPayload());
        }

        $valid = $workflow->status !== ReportStatus::Ditolak && $workflow->submitted_at !== null;
        $signerName = $workflow->submitter?->employee?->name ?? $workflow->submitter?->name ?? '—';

        return view('reports.signature-verify', $this->buildPayload(
            workflow: $workflow,
            valid: $valid,
            signerName: $signerName,
            signerPosition: 'Staf Operasi',
            signerRole: 'Dibuat Oleh',
            signedAt: $workflow->submitted_at,
            note: null,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(
        ReportWorkflow $workflow,
        bool $valid,
        string $signerName,
        string $signerPosition,
        string $signerRole,
        ?CarbonInterface $signedAt,
        ?string $note,
    ): array {
        $status = $workflow->status;
        $reason = match (true) {
            $valid => 'Tanda tangan elektronik tercatat sah di sistem dan belum pernah dibatalkan.',
            $status === ReportStatus::Ditolak => 'Dokumen telah ditolak pada tahap review ('.$workflow->rejection_reason.'). Semua pengesahan sebelumnya dinyatakan tidak berlaku.',
            default => 'Tahap tanda tangan atau pengesahan dokumen ini belum diselesaikan.',
        };

        $chain = $workflow->steps
            ->where('stage', ReportWorkflowStep::STAGE_PENGESAHAN)
            ->sortBy('sequence')
            ->map(fn (ReportWorkflowStep $s): array => [
                'caption' => $s->caption,
                'position' => $s->position,
                'name' => $s->employee?->name ?? $s->signer?->name ?? '—',
                'signed' => $status !== ReportStatus::Ditolak && $s->signed_at !== null,
                'signed_at' => $s->signed_at?->timezone(config('app.timezone'))->format('d M Y, H:i'),
            ])
            ->values()
            ->all();

        return [
            'found' => true,
            'valid' => $valid,
            'statusBadge' => $valid ? 'SAH & TERVERIFIKASI' : ($status === ReportStatus::Ditolak ? 'TIDAK BERLAKU (DITOLAK)' : 'BELUM DISAHKAN'),
            'reason' => $reason,
            'documentTitle' => $workflow->module->label(),
            'unitName' => $workflow->unit?->name ?? '—',
            'period' => Indonesian::monthName($workflow->month).' '.$workflow->year,
            'workflowStatus' => $status->label(),
            'signerName' => $signerName,
            'signerPosition' => $signerPosition,
            'signerRole' => $signerRole,
            'signedAt' => $signedAt?->timezone(config('app.timezone'))->format('d M Y, H:i \W\I\T\A'),
            'note' => $note,
            'chain' => $chain,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invalidPayload(): array
    {
        return [
            'found' => false,
            'valid' => false,
            'statusBadge' => 'TIDAK VALID',
            'reason' => 'Kode verifikasi tanda tangan tidak ditemukan atau tidak sesuai dengan catatan sistem.',
            'documentTitle' => null,
            'unitName' => null,
            'period' => null,
            'workflowStatus' => null,
            'signerName' => null,
            'signerPosition' => null,
            'signerRole' => null,
            'signedAt' => null,
            'note' => null,
            'chain' => [],
        ];
    }
}
