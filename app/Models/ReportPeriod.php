<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\ReportPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reporting period (one month) for one unit.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $month
 * @property int $year
 * @property int $total_days
 * @property int $total_hours
 * @property int|null $pic_employee_id
 * @property Carbon|null $locked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Employee|null $picEmployee
 */
#[Fillable(['unit_id', 'month', 'year', 'total_days', 'total_hours', 'pic_employee_id', 'locked_at'])]
class ReportPeriod extends Model
{
    /** @use HasFactory<ReportPeriodFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function picEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_employee_id');
    }
}
