<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A material used in a maintenance activity.
 *
 * @property int $id
 * @property int $activity_id
 * @property string $material_name
 * @property string|null $part_number
 * @property string|null $quantity
 * @property string|null $unit_of_measure
 */
#[Fillable(['activity_id', 'material_name', 'part_number', 'quantity', 'unit_of_measure'])]
class MaintenanceActivityMaterial extends Model
{
    /**
     * @return BelongsTo<MaintenanceActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(MaintenanceActivity::class, 'activity_id');
    }
}
