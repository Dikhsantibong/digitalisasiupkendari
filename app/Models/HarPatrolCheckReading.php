<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu hari pembacaan Patrol Check Parameter Mesin (per mesin).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $machine_id
 * @property int $year
 * @property int $month
 * @property int $day
 * @property array<string, string> $values
 */
#[Fillable(['unit_id', 'machine_id', 'year', 'month', 'day', 'values', 'input_by'])]
class HarPatrolCheckReading extends Model
{
    use BelongsToUnit;

    protected $table = 'har_patrol_check_readings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'day' => 'integer',
            'values' => 'array',
        ];
    }
}
