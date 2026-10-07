<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Monthly central overview and fuel/lubricant inventory report per unit.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $month
 * @property int $year
 * @property float $kwh_dibangkit
 * @property float $kwh_pemakaian_sendiri
 * @property float $kwh_disalurkan
 * @property float $beban_puncak_pagi_kw
 * @property float $beban_puncak_malam_kw
 * @property float $jam_jalan_perhari
 * @property array|null $persediaan_awal
 * @property array|null $penerimaan
 * @property array|null $penerimaan_sewa_smp
 * @property array|null $pemakaian_non_operasi
 * @property array|null $pengiriman
 * @property string|null $catatan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read User|null $inputBy
 * @property-read Collection<int, OperasiIkhtisarSentralMesin> $mesins
 */
#[Fillable([
    'unit_id',
    'month',
    'year',
    'kwh_dibangkit',
    'kwh_pemakaian_sendiri',
    'kwh_disalurkan',
    'beban_puncak_pagi_kw',
    'beban_puncak_malam_kw',
    'jam_jalan_perhari',
    'persediaan_awal',
    'penerimaan',
    'penerimaan_sewa_smp',
    'pemakaian_non_operasi',
    'pengiriman',
    'catatan',
    'input_by',
])]
class OperasiIkhtisarSentral extends Model
{
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'kwh_dibangkit' => 'float',
            'kwh_pemakaian_sendiri' => 'float',
            'kwh_disalurkan' => 'float',
            'beban_puncak_pagi_kw' => 'float',
            'beban_puncak_malam_kw' => 'float',
            'jam_jalan_perhari' => 'float',
            'persediaan_awal' => 'array',
            'penerimaan' => 'array',
            'penerimaan_sewa_smp' => 'array',
            'pemakaian_non_operasi' => 'array',
            'pengiriman' => 'array',
        ];
    }

    /**
     * @return HasMany<OperasiIkhtisarSentralMesin, $this>
     */
    public function mesins(): HasMany
    {
        return $this->hasMany(OperasiIkhtisarSentralMesin::class, 'ikhtisar_sentral_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
