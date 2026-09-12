<?php

namespace App\Models;

use Database\Factories\WorkScheduleEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One cell of a schedule grid: an employee's attendance code on one day.
 *
 * @property int $id
 * @property int $work_schedule_id
 * @property int $employee_id
 * @property Carbon $work_date
 * @property int|null $attendance_code_id
 * @property string|null $regu
 * @property string|null $note
 * @property-read WorkSchedule $schedule
 * @property-read Employee $employee
 * @property-read AttendanceCode|null $attendanceCode
 */
#[Fillable([
    'work_schedule_id', 'employee_id', 'work_date', 'attendance_code_id', 'regu', 'note',
])]
class WorkScheduleEntry extends Model
{
    /** @use HasFactory<WorkScheduleEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<WorkSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<AttendanceCode, $this>
     */
    public function attendanceCode(): BelongsTo
    {
        return $this->belongsTo(AttendanceCode::class);
    }
}
