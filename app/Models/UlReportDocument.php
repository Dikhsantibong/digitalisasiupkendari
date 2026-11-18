<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Services\Reports\UlReportWorkflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The editable document of a Laporan UL (Pengusahaan Pembangkit, Logistik,
 * K3L) of a service unit for one month, with its approval status: submitted
 * by the Admin UL, approved by one TL per bidang, ratified by the Manager UL
 * ({@see UlReportWorkflow}).
 *
 * @property int $id
 * @property int $service_unit_id
 * @property string $kind
 * @property int $month
 * @property int $year
 * @property string|null $document_number
 * @property string $format
 * @property string|null $content_html
 * @property array<string, mixed>|null $content_grid
 * @property int $content_version
 * @property ReportStatus $status
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property int|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property Carbon|null $finalized_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read ServiceUnit $serviceUnit
 * @property-read User|null $submitter
 * @property-read User|null $rejecter
 * @property-read Collection<int, UlReportStep> $steps
 * @property-read Collection<int, UlReportLog> $logs
 */
#[Fillable([
    'service_unit_id', 'kind', 'month', 'year', 'document_number', 'format', 'content_html', 'content_grid', 'content_version',
    'status', 'submitted_by', 'submitted_at', 'rejected_by', 'rejected_at', 'rejection_reason', 'finalized_at', 'created_by', 'updated_by',
])]
class UlReportDocument extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'content_grid' => 'array',
            'content_version' => 'integer',
            'status' => ReportStatus::class,
            'submitted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ServiceUnit, $this>
     */
    public function serviceUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceUnit::class);
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
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return HasMany<UlReportStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(UlReportStep::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<UlReportLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(UlReportLog::class)->orderBy('id');
    }
}
