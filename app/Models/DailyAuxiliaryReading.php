<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\DailyAuxiliaryReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An auxiliary source's daily closing readings.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $auxiliary_source_id
 * @property Carbon $report_date
 * @property string|null $stand_kwh_akhir
 * @property string|null $stand_bbm_akhir
 * @property int|null $input_by
 * @property-read Unit $unit
 * @property-read AuxiliarySource $auxiliarySource
 */
#[Fillable(['unit_id', 'auxiliary_source_id', 'report_date', 'stand_kwh_akhir', 'stand_bbm_akhir', 'input_by'])]
class DailyAuxiliaryReading extends Model
{
    /** @use HasFactory<DailyAuxiliaryReadingFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<AuxiliarySource, $this>
     */
    public function auxiliarySource(): BelongsTo
    {
        return $this->belongsTo(AuxiliarySource::class);
    }
}
