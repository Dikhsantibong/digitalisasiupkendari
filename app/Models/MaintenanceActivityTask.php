<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single task line under a maintenance activity.
 *
 * @property int $id
 * @property int $activity_id
 * @property string $task_description
 * @property int $sort_order
 */
#[Fillable(['activity_id', 'task_description', 'sort_order'])]
class MaintenanceActivityTask extends Model
{
    /**
     * @return BelongsTo<MaintenanceActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(MaintenanceActivity::class, 'activity_id');
    }
}
