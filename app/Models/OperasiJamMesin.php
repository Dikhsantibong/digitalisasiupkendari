<?php

namespace App\Models;

use App\Enums\JamMesinJenis;
use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A monthly machine-hour sheet of a unit (Jam Operasi, Jam Pemeliharaan or
 * Jam Gangguan): hours per mesin per tanggal, keyed by machine id then day.
 *
 * @property int $id
 * @property int $unit_id
 * @property JamMesinJenis $jenis
 * @property int $month
 * @property int $year
 * @property array<int|string, array<int|string, float>>|null $readings
 * @property array<int|string, float>|null $totals_by_machine
 * @property string $grand_total
 * @property string|null $catatan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'jenis', 'month', 'year', 'readings', 'totals_by_machine', 'grand_total', 'catatan', 'input_by'])]
class OperasiJamMesin extends Model
{
    use BelongsToUnit;

    protected $table = 'operasi_jam_mesin';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JamMesinJenis::class,
            'month' => 'integer',
            'year' => 'integer',
            'readings' => 'array',
            'totals_by_machine' => 'array',
            'grand_total' => 'decimal:2',
        ];
    }
}
