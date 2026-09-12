<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo attachment for maintenance work (stored on disk; only the path here).
 *
 * @property int $id
 * @property int $unit_id
 * @property int|null $report_period_id
 * @property int|null $wo_id
 * @property int|null $activity_id
 * @property int|null $engine_id
 * @property string $title
 * @property string $photo_path
 */
#[Fillable([
    'unit_id', 'report_period_id', 'wo_id', 'activity_id', 'engine_id',
    'title', 'photo_path', 'caption', 'taken_date', 'sort_order', 'input_by',
])]
class MaintenanceAttachment extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'wo_id');
    }

    /**
     * @return BelongsTo<MaintenanceActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(MaintenanceActivity::class, 'activity_id');
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function engine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'engine_id');
    }
}
