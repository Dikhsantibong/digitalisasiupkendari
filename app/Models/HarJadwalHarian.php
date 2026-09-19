<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Kegiatan Harian Pemeliharaan per unit, bulan, dan tahun.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int|null $no_urut
 * @property string $kegiatan
 * @property int $target
 * @property int $rencana_count
 * @property int $realisasi_count
 * @property array<int>|null $jadwal
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
    'kegiatan',
    'target',
    'rencana_count',
    'realisasi_count',
    'jadwal',
    'keterangan',
    'sort_order',
    'input_by',
])]
class HarJadwalHarian extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'har_jadwal_harians';

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
            'target' => 'integer',
            'rencana_count' => 'integer',
            'realisasi_count' => 'integer',
            'jadwal' => 'array',
            'sort_order' => 'integer',
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
