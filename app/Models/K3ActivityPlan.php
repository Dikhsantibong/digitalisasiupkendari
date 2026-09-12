<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Time Frame entry: the planned vs realised days for one K3 activity in a
 * unit/month. Day marks are kept as flexible JSON.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $k3_activity_type_id
 * @property string|null $pic
 * @property array<string, mixed>|null $plan_days
 * @property array<string, mixed>|null $real_days
 * @property string|null $status
 */
#[Fillable([
    'unit_id', 'year', 'month', 'k3_activity_type_id', 'pic',
    'plan_days', 'real_days', 'status', 'keterangan', 'input_by',
])]
class K3ActivityPlan extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_days' => 'array',
            'real_days' => 'array',
        ];
    }

    /**
     * @return BelongsTo<K3ActivityType, $this>
     */
    public function activityType(): BelongsTo
    {
        return $this->belongsTo(K3ActivityType::class, 'k3_activity_type_id');
    }
}
