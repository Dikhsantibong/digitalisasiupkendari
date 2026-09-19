<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One signer of a report workflow, frozen to the employee holding the jabatan
 * in the report's unit when it was submitted: an approval step (stage
 * "pengesahan": 1 Koordinator memeriksa, 2 Team Leader modul menyetujui,
 * 3 Manager UL mengesahkan) or an in-report signer (stage "tanda_tangan"),
 * which is not acted on and prints once the report is FINAL.
 *
 * @property int $id
 * @property int $report_workflow_id
 * @property string $stage
 * @property int $sequence
 * @property string $caption
 * @property string $position
 * @property int|null $employee_id
 * @property int|null $signed_by
 * @property Carbon|null $signed_at
 * @property string|null $note
 * @property-read Employee|null $employee
 * @property-read User|null $signer
 */
#[Fillable(['report_workflow_id', 'stage', 'sequence', 'caption', 'position', 'employee_id', 'signed_by', 'signed_at', 'note'])]
class ReportWorkflowStep extends Model
{
    public const STAGE_PENGESAHAN = 'pengesahan';

    public const STAGE_TANDA_TANGAN = 'tanda_tangan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'signed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ReportWorkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ReportWorkflow::class, 'report_workflow_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function isPengesahan(): bool
    {
        return $this->stage === self::STAGE_PENGESAHAN;
    }
}
