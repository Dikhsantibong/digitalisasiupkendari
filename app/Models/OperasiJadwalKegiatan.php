<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Kegiatan Operasi (Harian, Mingguan, Bulanan & Non Rutin) per unit,
 * bulan, dan tahun (2026-11-25). Satu baris = satu kegiatan pada salah satu grup.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string $grup
 * @property int|null $no_urut
 * @property string $kegiatan
 * @property int $target
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
    'grup',
    'no_urut',
    'kegiatan',
    'target',
    'jadwal',
    'keterangan',
    'sort_order',
    'input_by',
])]
class OperasiJadwalKegiatan extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_jadwal_kegiatans';

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
