<?php

namespace App\Services\Reports;

use App\Enums\SignatureMode;
use App\Models\Employee;
use App\Models\ReportWorkflow;
use App\Models\ReportWorkflowStep;

/**
 * Default signature mark resolver supporting pluggable display modes ('qr', 'image', 'text').
 */
class WorkflowSignatureMarkResolver implements SignatureMarkResolver
{
    public function __construct(
        private readonly SignatureQrService $qr,
        private readonly ReportSignatories $signatories,
    ) {}

    public function forStep(ReportWorkflowStep $step, ?Employee $employee, ?string $signedAt): array
    {
        return $this->resolve($employee, $signedAt, fn (): string => $this->qr->verifyUrlForStep($step));
    }

    public function forSubmission(ReportWorkflow $workflow, ?Employee $employee, ?string $signedAt): array
    {
        return $this->resolve($employee, $signedAt, fn (): string => $this->qr->verifyUrlForSubmission($workflow));
    }

    /**
     * @param  callable(): string  $buildVerifyUrl
     * @return array{mode: string, qr: ?string, image: ?string, verify_url: ?string, signed_at: ?string}
     */
    private function resolve(?Employee $employee, ?string $signedAt, callable $buildVerifyUrl): array
    {
        $mode = SignatureMode::tryFrom((string) config('reports.signature_mode', SignatureMode::Qr->value))
            ?? SignatureMode::Qr;

        $image = $this->signatories->signatureImage($employee);
        $verifyUrl = $mode === SignatureMode::Text ? null : $buildVerifyUrl();
        $needsQr = $mode === SignatureMode::Qr || ($mode === SignatureMode::Image && $image === null);

        return [
            'mode' => $mode->value,
            'qr' => $needsQr && $verifyUrl !== null ? $this->qr->pngDataUri($verifyUrl) : null,
            'image' => $image,
            'verify_url' => $verifyUrl,
            'signed_at' => $signedAt,
        ];
    }
}
