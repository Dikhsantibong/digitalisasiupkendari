<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\ReportWorkflow;
use App\Models\ReportWorkflowStep;

/**
 * Resolves how an approved signature slot is represented in report documents.
 * Swap this binding in AppServiceProvider when changing signature providers or models.
 */
interface SignatureMarkResolver
{
    /**
     * Resolve signature display data for an approved ReportWorkflowStep.
     *
     * @return array{mode: string, qr: ?string, image: ?string, verify_url: ?string, signed_at: ?string}
     */
    public function forStep(ReportWorkflowStep $step, ?Employee $employee, ?string $signedAt): array;

    /**
     * Resolve signature display data for the initial report submission (e.g. Pengusahaan creator).
     *
     * @return array{mode: string, qr: ?string, image: ?string, verify_url: ?string, signed_at: ?string}
     */
    public function forSubmission(ReportWorkflow $workflow, ?Employee $employee, ?string $signedAt): array;
}
