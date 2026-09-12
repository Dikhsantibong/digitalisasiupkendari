<?php

namespace App\Models;

use App\Enums\ScheduleGroupType;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\WorkScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A monthly work-schedule header for one unit and employee group. The daily
 * cells live in {@see WorkScheduleEntry}; recap & percentage are derived.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property ScheduleGroupType $group_type
 * @property Carbon|null $generated_at
 * @property Carbon|null $locked_at
 * @property int|null $input_by
 * @property-read Collection<int, WorkScheduleEntry> $entries
 */
#[Fillable([
    'unit_id', 'year', 'month', 'group_type', 'generated_at', 'locked_at', 'input_by',
])]
class WorkSchedule extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<WorkScheduleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group_type' => ScheduleGroupType::class,
            'generated_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<WorkScheduleEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(WorkScheduleEntry::class);
    }
}
