<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use App\Enums\WorkOrderSource;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Service Request. Manual for now; read through the WorkOrderSource interface.
 *
 * @property int $id
 * @property int $unit_id
 * @property int|null $report_period_id
 * @property string $sr_number
 * @property string|null $description
 * @property int|null $sr_category_id
 * @property ServiceRequestStatus $status
 * @property int|null $engine_id
 * @property WorkOrderSource $source
 */
#[Fillable([
    'unit_id', 'report_period_id', 'sr_number', 'description', 'sr_category_id',
    'status', 'engine_id', 'source', 'keterangan', 'input_by',
])]
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ServiceRequestStatus::class,
            'source' => WorkOrderSource::class,
        ];
    }

    /**
     * @return BelongsTo<SrCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SrCategory::class, 'sr_category_id');
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function engine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'engine_id');
    }
}
