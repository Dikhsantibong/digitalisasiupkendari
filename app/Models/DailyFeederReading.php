<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\DailyFeederReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A feeder's daily closing meter reading.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $feeder_id
 * @property Carbon $report_date
 * @property string|null $stand_akhir
 * @property bool $is_active_today
 * @property int|null $input_by
 * @property-read Unit $unit
 * @property-read Feeder $feeder
 */
#[Fillable(['unit_id', 'feeder_id', 'report_date', 'stand_akhir', 'is_active_today', 'input_by'])]
class DailyFeederReading extends Model
{
    /** @use HasFactory<DailyFeederReadingFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'is_active_today' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Feeder, $this>
     */
    public function feeder(): BelongsTo
    {
        return $this->belongsTo(Feeder::class);
    }
}
