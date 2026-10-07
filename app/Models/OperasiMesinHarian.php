<?php

namespace App\Models;

use App\Enums\MesinHarianJenis;
use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A monthly per-machine daily sheet (Beban Tertinggi or Jumlah Kali
 * Gangguan): values keyed by machine id then day, and the sheet's machine
 * parameters (the daya mampu of each machine for Beban Tertinggi).
 *
 * @property int $id
 * @property int $unit_id
 * @property MesinHarianJenis $jenis
 * @property int $month
 * @property int $year
 * @property array<int|string, array<int|string, float>>|null $readings
 * @property array<string, mixed>|null $params
 * @property string|null $catatan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'jenis', 'month', 'year', 'readings', 'params', 'catatan', 'input_by'])]
class OperasiMesinHarian extends Model
{
    use BelongsToUnit;

    protected $table = 'operasi_mesin_harian';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => MesinHarianJenis::class,
            'month' => 'integer',
            'year' => 'integer',
            'readings' => 'array',
            'params' => 'array',
        ];
    }
}
