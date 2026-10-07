<?php

namespace App\Models;

use App\Enums\TugJenis;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\OperasiTugFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The header of a TUG 9 (Rekap Bon Pemakaian Energi Primer) of one machine
 * for one month — pelumas or BBM. The amounts are not stored here: they come
 * from the Pemakaian Pelumas / Pemakaian Bahan Bakar sheet of that month.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $machine_id
 * @property TugJenis $jenis
 * @property int $month
 * @property int $year
 * @property string|null $nomor
 * @property string $pekerjaan
 * @property string|null $no_spk
 * @property string|null $cost_center
 * @property string|null $kode_perkiraan
 * @property Carbon|null $tanggal_dokumen
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Machine $machine
 */
#[Fillable(['unit_id', 'machine_id', 'jenis', 'month', 'year', 'nomor', 'pekerjaan', 'no_spk', 'cost_center', 'kode_perkiraan', 'tanggal_dokumen', 'input_by'])]
class OperasiTug extends Model
{
    /** @use HasFactory<OperasiTugFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => TugJenis::class,
            'month' => 'integer',
            'year' => 'integer',
            'tanggal_dokumen' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
