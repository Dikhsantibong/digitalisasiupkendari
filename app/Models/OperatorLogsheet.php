<?php

namespace App\Models;

use App\Enums\LogsheetStatus;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\OperatorLogsheetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One operator logsheet: a single machine's hourly readings for one day. Locked
 * from operator edits once submitted.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $engine_id
 * @property Carbon $log_date
 * @property int|null $operator_employee_id
 * @property string|null $operator_name
 * @property string|null $shift
 * @property LogsheetStatus $status
 * @property Carbon|null $submitted_at
 */
#[Fillable([
    'unit_id', 'engine_id', 'log_date', 'operator_employee_id', 'operator_name',
    'shift', 'status', 'submitted_at', 'input_by',
])]
class OperatorLogsheet extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<OperatorLogsheetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'status' => LogsheetStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function engine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'engine_id');
    }

    /**
     * @return HasMany<OperatorLogsheetReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(OperatorLogsheetReading::class, 'logsheet_id');
    }
}
