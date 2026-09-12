<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\ShiftPatternFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The rotation pattern for one regu of one unit. `sequence` is a comma-separated
 * list of attendance codes repeated across the month by the generator.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $regu
 * @property string $sequence
 * @property int $cycle_days
 * @property bool $is_active
 */
#[Fillable([
    'unit_id', 'regu', 'sequence', 'cycle_days', 'is_active',
])]
class ShiftPattern extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<ShiftPatternFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The pattern's codes as an ordered list (blanks trimmed).
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return collect(explode(',', $this->sequence))
            ->map(fn (string $code): string => trim($code))
            ->filter(fn (string $code): bool => $code !== '')
            ->values()
            ->all();
    }
}
