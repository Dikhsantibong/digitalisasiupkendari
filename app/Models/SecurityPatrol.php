<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A security patrol log for one checkpoint on one day. The monthly cumulative
 * recap sums total_scan per location.
 *
 * @property int $id
 * @property int $unit_id
 * @property Carbon $patrol_date
 * @property int $patrol_location_id
 * @property array<int, mixed>|null $scan_times
 * @property int $total_scan
 */
#[Fillable(['unit_id', 'patrol_date', 'patrol_location_id', 'scan_times', 'total_scan', 'input_by'])]
class SecurityPatrol extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patrol_date' => 'date',
            'scan_times' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PatrolLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(PatrolLocation::class, 'patrol_location_id');
    }
}
