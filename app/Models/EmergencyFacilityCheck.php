<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An emergency-facility readiness check for one equipment in a unit/month, either
 * weekly (week 1-4) or monthly (week null). Readiness % is derived from
 * ready/total on read, not stored.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int|null $week
 * @property int $emergency_equipment_id
 * @property int $jml_total
 * @property int $jml_ready
 * @property int $jml_not_ready
 */
#[Fillable([
    'unit_id', 'year', 'month', 'week', 'emergency_equipment_id',
    'jml_total', 'jml_ready', 'jml_not_ready', 'kendala', 'tindak_lanjut', 'input_by',
])]
class EmergencyFacilityCheck extends Model
{
    use BelongsToUnit;

    /**
     * @return BelongsTo<EmergencyEquipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(EmergencyEquipment::class, 'emergency_equipment_id');
    }
}
