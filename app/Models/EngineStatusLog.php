<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\EngineStatusLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Star-Stop entry: the source of machine hours.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $engine_id
 * @property Carbon $report_date
 * @property int $status_code_id
 * @property string|null $operator_name
 * @property string|null $dispatcher_name
 * @property Carbon|null $start_datetime
 * @property Carbon|null $stop_datetime
 * @property int|null $duration_minutes
 * @property string|null $keterangan
 * @property int|null $input_by
 * @property-read Unit $unit
 * @property-read Machine $engine
 * @property-read UnitStatusCode $statusCode
 */
#[Fillable([
    'unit_id', 'engine_id', 'report_date', 'status_code_id', 'operator_name',
    'dispatcher_name', 'start_datetime', 'stop_datetime', 'duration_minutes',
    'keterangan', 'input_by',
])]
class EngineStatusLog extends Model
{
    /** @use HasFactory<EngineStatusLogFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'start_datetime' => 'datetime',
            'stop_datetime' => 'datetime',
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
     * @return BelongsTo<UnitStatusCode, $this>
     */
    public function statusCode(): BelongsTo
    {
        return $this->belongsTo(UnitStatusCode::class, 'status_code_id');
    }
}
