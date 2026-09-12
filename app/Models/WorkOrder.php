<?php

namespace App\Models;

use App\Enums\WorkOrderSource;
use App\Enums\WoWaitingReason;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Work Order. Manual for now (see `source`); read only through the
 * {@see \App\Services\Har\WorkOrderSource} interface so WPC can supply rows later.
 *
 * @property int $id
 * @property int $unit_id
 * @property int|null $report_period_id
 * @property string $wonum
 * @property string|null $description
 * @property int|null $maintenance_type_id
 * @property int|null $engine_id
 * @property int|null $work_group_id
 * @property int|null $wo_status_id
 * @property int|null $cycle_id
 * @property WoWaitingReason|null $waiting_reason
 * @property WorkOrderSource $source
 */
#[Fillable([
    'unit_id', 'report_period_id', 'wonum', 'description', 'maintenance_type_id',
    'engine_id', 'work_group_id', 'wo_status_id', 'cycle_id', 'report_date',
    'sched_start', 'sched_finish', 'actual_finish', 'waiting_reason',
    'service_cost', 'material_cost', 'source', 'input_by',
])]
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'datetime',
            'sched_start' => 'datetime',
            'sched_finish' => 'datetime',
            'actual_finish' => 'datetime',
            'waiting_reason' => WoWaitingReason::class,
            'source' => WorkOrderSource::class,
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
        return $this->belongsTo(MaintenanceType::class);
    }

    /**
     * @return BelongsTo<WorkGroup, $this>
     */
    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(WorkGroup::class);
    }

    /**
     * @return BelongsTo<WoStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(WoStatus::class, 'wo_status_id');
    }

    /**
     * @return BelongsTo<MaintenanceCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MaintenanceCycle::class, 'cycle_id');
    }
}
