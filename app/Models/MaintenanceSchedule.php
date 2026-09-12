<?php

namespace App\Models;

use App\Enums\MaintenanceScope;
use App\Enums\SchedulePlanType;
use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A plan-vs-realisation schedule matrix (kept as JSON) per machine and scope.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int|null $engine_id
 * @property SchedulePlanType $plan_type
 * @property MaintenanceScope $scope
 * @property array<string, mixed>|null $schedule_data
 */
#[Fillable(['unit_id', 'year', 'month', 'engine_id', 'plan_type', 'scope', 'schedule_data', 'input_by'])]
class MaintenanceSchedule extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_type' => SchedulePlanType::class,
            'scope' => MaintenanceScope::class,
            'schedule_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function engine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'engine_id');
    }
}
