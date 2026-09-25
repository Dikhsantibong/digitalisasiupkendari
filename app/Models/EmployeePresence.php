<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\EmployeePresenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An employee's absen masuk / absen pulang for one work date, taken within the
 * unit's office radius.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $employee_id
 * @property int|null $user_id
 * @property Carbon $work_date
 * @property string|null $shift_code
 * @property Carbon $check_in_at
 * @property string $check_in_latitude
 * @property string $check_in_longitude
 * @property int $check_in_distance_m
 * @property int|null $check_in_accuracy_m
 * @property int|null $late_minutes
 * @property string|null $check_in_note
 * @property Carbon|null $check_out_at
 * @property string|null $check_out_latitude
 * @property string|null $check_out_longitude
 * @property int|null $check_out_distance_m
 * @property int|null $check_out_accuracy_m
 */
#[Fillable([
    'unit_id',
    'employee_id',
    'user_id',
    'work_date',
    'shift_code',
    'check_in_at',
    'check_in_latitude',
    'check_in_longitude',
    'check_in_distance_m',
    'check_in_accuracy_m',
    'late_minutes',
    'check_in_note',
    'check_out_at',
    'check_out_latitude',
    'check_out_longitude',
    'check_out_distance_m',
    'check_out_accuracy_m',
])]
class EmployeePresence extends Model
{
    /** @use HasFactory<EmployeePresenceFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
