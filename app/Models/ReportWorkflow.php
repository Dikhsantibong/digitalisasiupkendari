<?php

namespace App\Models;

use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\ReportWorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The verification & pengesahan workflow of one Laporan Pembangkit
 * (module + unit + month): its status, who submitted / verified / rejected
 * it, its signing steps and its audit trail.
 *
 * @property int $id
 * @property ReportModule $module
 * @property int $unit_id
 * @property int $month
 * @property int $year
 * @property ReportStatus $status
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $verification_note
 * @property int|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property Carbon|null $finalized_at
 * @property-read Unit $unit
 * @property-read Collection<int, ReportWorkflowStep> $steps
 * @property-read Collection<int, ReportWorkflowLog> $logs
 */
#[Fillable([
    'module', 'unit_id', 'month', 'year', 'status',
    'submitted_by', 'submitted_at', 'verified_by', 'verified_at', 'verification_note',
    'rejected_by', 'rejected_at', 'rejection_reason', 'finalized_at',
])]
class ReportWorkflow extends Model
{
    /** @use HasFactory<ReportWorkflowFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module' => ReportModule::class,
            'status' => ReportStatus::class,
            'month' => 'integer',
            'year' => 'integer',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * Pengesahan steps first, then the in-report tanda tangan, each in order.
     *
     * @return HasMany<ReportWorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ReportWorkflowStep::class)
            ->orderByRaw("case when stage = 'pengesahan' then 0 else 1 end")
            ->orderBy('sequence');
    }

    /**
     * @return HasMany<ReportWorkflowLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ReportWorkflowLog::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * The next step waiting for its signer, in workflow order.
     */
    public function currentStep(): ?ReportWorkflowStep
    {
        return $this->steps->first(fn (ReportWorkflowStep $step): bool => $step->signed_at === null);
    }
}
