<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Logbook Pemantauan Pemanfaatan Air Limbah (modul K3).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int|null $no_urut
 * @property string $area_penyiraman
 * @property Carbon|null $tanggal
 * @property string|null $waktu_penyiraman
 * @property string|null $metode_pemanfaatan
 * @property float $debit_awal
 * @property float $debit_akhir
 * @property float $debit_jumlah
 * @property string|null $frekuensi
 * @property string|null $pic
 * @property string|null $keterangan
 * @property int $sort_order
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read User|null $inputUser
 */
#[Fillable([
    'unit_id',
    'year',
    'month',
    'no_urut',
    'area_penyiraman',
    'tanggal',
    'waktu_penyiraman',
    'metode_pemanfaatan',
    'debit_awal',
    'debit_akhir',
    'debit_jumlah',
    'frekuensi',
    'pic',
    'keterangan',
    'sort_order',
    'input_by',
])]
class K3AirLimbah extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'k3_air_limbahs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'no_urut' => 'integer',
            'tanggal' => 'date',
            'debit_awal' => 'float',
            'debit_akhir' => 'float',
            'debit_jumlah' => 'float',
            'sort_order' => 'integer',
            'input_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
