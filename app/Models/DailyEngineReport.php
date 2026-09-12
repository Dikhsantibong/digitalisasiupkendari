<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\DailyEngineReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One machine's daily meter readings. Opening stands are not stored — they
 * carry over from the previous day.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $engine_id
 * @property Carbon $report_date
 * @property string|null $kwh_produksi_stand_akhir
 * @property string|null $kwh_pakai_sendiri_stand_akhir
 * @property string|null $beban_puncak_pagi_kw
 * @property string|null $beban_puncak_malam_kw
 * @property string|null $pemakaian_pelumas_liter
 * @property string|null $flowmeter_hsd_stand_akhir
 * @property string|null $flowmeter_hsd_tambah_liter
 * @property string|null $flowmeter_mfo_stand_akhir
 * @property string|null $flowmeter_mfo_tambah_liter
 * @property string|null $air_pps_stand_akhir
 * @property string|null $air_softener_stand_akhir
 * @property string|null $catatan
 * @property int|null $input_by
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property-read Unit $unit
 * @property-read Machine $engine
 */
#[Fillable([
    'unit_id', 'engine_id', 'report_date', 'source',
    'kwh_produksi_stand_akhir', 'kwh_pakai_sendiri_stand_akhir',
    'beban_puncak_pagi_kw', 'beban_puncak_malam_kw', 'pemakaian_pelumas_liter',
    'flowmeter_hsd_stand_akhir', 'flowmeter_hsd_tambah_liter',
    'flowmeter_mfo_stand_akhir', 'flowmeter_mfo_tambah_liter',
    'air_pps_stand_akhir', 'air_softener_stand_akhir',
    'catatan', 'input_by', 'verified_by', 'verified_at',
])]
class DailyEngineReport extends Model
{
    /** @use HasFactory<DailyEngineReportFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'verified_at' => 'datetime',
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
