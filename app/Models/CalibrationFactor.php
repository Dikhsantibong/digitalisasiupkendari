<?php

namespace App\Models;

use App\Enums\CalibrationFactorType;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\CalibrationFactorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-unit (optionally per-machine) calibration factor.
 *
 * @property int $id
 * @property int $unit_id
 * @property int|null $engine_id
 * @property CalibrationFactorType $factor_type
 * @property string $value
 * @property Carbon $effective_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Machine|null $engine
 */
#[Fillable(['unit_id', 'engine_id', 'factor_type', 'value', 'effective_date', 'notes'])]
class CalibrationFactor extends Model
{
    /** @use HasFactory<CalibrationFactorFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor_type' => CalibrationFactorType::class,
            'effective_date' => 'date',
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
