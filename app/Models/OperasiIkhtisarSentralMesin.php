<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Breakdown of central overview per machine.
 *
 * @property int $id
 * @property int $ikhtisar_sentral_id
 * @property int $unit_id
 * @property int $engine_id
 * @property float $kwh_dibangkit
 * @property float $jam_jalan
 * @property float $pemakaian_hsd
 * @property float $pemakaian_mfo
 * @property float $sfc
 * @property float $t_kalor
 * @property float $slc
 * @property array|null $pemakaian_pelumas
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OperasiIkhtisarSentral $ikhtisarSentral
 * @property-read Machine $engine
 * @property-read Unit $unit
 */
#[Fillable([
    'ikhtisar_sentral_id',
    'unit_id',
    'engine_id',
    'kwh_dibangkit',
    'jam_jalan',
    'pemakaian_hsd',
    'pemakaian_mfo',
    'pemakaian_bbm',
    'sfc',
    't_kalor',
    'slc',
    'pemakaian_pelumas',
])]
class OperasiIkhtisarSentralMesin extends Model
{
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kwh_dibangkit' => 'float',
            'jam_jalan' => 'float',
            'pemakaian_hsd' => 'float',
            'pemakaian_mfo' => 'float',
            'pemakaian_bbm' => 'array',
            'sfc' => 'float',
            't_kalor' => 'float',
            'slc' => 'float',
            'pemakaian_pelumas' => 'array',
        ];
    }

    /**
     * @return BelongsTo<OperasiIkhtisarSentral, $this>
     */
    public function ikhtisarSentral(): BelongsTo
    {
        return $this->belongsTo(OperasiIkhtisarSentral::class, 'ikhtisar_sentral_id');
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function engine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'engine_id');
    }
}
