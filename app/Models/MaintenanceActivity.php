<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A HARMES activity-log entry (the core field-work record) with its task lines
 * and materials.
 *
 * @property int $id
 * @property int $unit_id
 * @property int|null $report_period_id
 * @property Carbon $activity_date
 * @property int|null $engine_id
 * @property int|null $maintenance_type_id
 * @property int|null $wo_id
 */
#[Fillable([
    'unit_id', 'report_period_id', 'activity_date', 'engine_id', 'maintenance_type_id',
    'wo_id', 'work_result', 'no_lh05', 'no_sr', 'no_tug9', 'keterangan', 'input_by',
])]
class MaintenanceActivity extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function engine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'engine_id');
    }

    /**
     * @return BelongsTo<MaintenanceType, $this>
     */
    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class, 'maintenance_type_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'wo_id');
    }

    /**
     * @return HasMany<MaintenanceActivityTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(MaintenanceActivityTask::class, 'activity_id');
    }

    /**
     * @return HasMany<MaintenanceActivityMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(MaintenanceActivityMaterial::class, 'activity_id');
    }
}
