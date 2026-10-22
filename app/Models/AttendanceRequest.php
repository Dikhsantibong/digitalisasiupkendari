<?php

namespace App\Models;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceRequestType;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\AttendanceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Pengajuan Absensi: izin, sakit or cuti over a date range, or a tukar shift
 * handing the employee's shift on `start_date` to the pengganti (who, with a
 * `swap_date`, gets the employee's work on that day in return).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $employee_id
 * @property int|null $requested_by
 * @property AttendanceRequestType $type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $reason
 * @property string|null $attachment_path surat dokter / bukti (public disk)
 * @property int|null $substitute_employee_id
 * @property Carbon|null $swap_date
 * @property AttendanceRequestStatus $status
 * @property Carbon|null $substitute_responded_at
 * @property string|null $substitute_note
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property list<array{employee_id: int, date: string, from: string|null, to: string|null}>|null $applied the Jadwal Shift cells the approval changed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Employee|null $substitute
 * @property-read User|null $requester
 * @property-read User|null $decider
 */
#[Fillable([
    'unit_id',
    'employee_id',
    'requested_by',
    'type',
    'start_date',
    'end_date',
    'reason',
    'attachment_path',
    'substitute_employee_id',
    'swap_date',
    'status',
    'substitute_responded_at',
    'substitute_note',
    'decided_by',
    'decided_at',
    'decision_note',
    'applied',
])]
class AttendanceRequest extends Model
{
    /** @use HasFactory<AttendanceRequestFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttendanceRequestType::class,
            'status' => AttendanceRequestStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'swap_date' => 'date',
            'substitute_responded_at' => 'datetime',
            'decided_at' => 'datetime',
            'applied' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function substitute(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'substitute_employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
