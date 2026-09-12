<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A periodic condition check of one APAR/APAB extinguisher for a unit/month.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $fire_extinguisher_id
 * @property Carbon|null $tgl_periksa
 * @property Carbon|null $exp_date
 */
#[Fillable([
    'unit_id', 'year', 'month', 'fire_extinguisher_id', 'tgl_periksa',
    'kondisi_tabung', 'kondisi_nozzle', 'indikator_tekanan', 'kondisi_pin_segel',
    'exp_date', 'keterangan', 'input_by',
])]
class FireExtinguisherCheck extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tgl_periksa' => 'date',
            'exp_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<FireExtinguisher, $this>
     */
    public function extinguisher(): BelongsTo
    {
        return $this->belongsTo(FireExtinguisher::class, 'fire_extinguisher_id');
    }
}
