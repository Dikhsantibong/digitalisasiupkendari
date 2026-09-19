<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Pembuatan IK Pemeliharaan Pembangkit per unit dan tahun.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int|null $no_urut
 * @property string $instruksi_kerja
 * @property string|null $pic_pembuat
 * @property array<int>|null $rencana_bulan
 * @property array<int>|null $realisasi_bulan
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
    'no_urut',
    'instruksi_kerja',
    'pic_pembuat',
    'rencana_bulan',
    'realisasi_bulan',
    'keterangan',
    'sort_order',
    'input_by',
])]
class HarJadwalPembuatanIk extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'har_jadwal_pembuatan_iks';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'no_urut' => 'integer',
            'rencana_bulan' => 'array',
            'realisasi_bulan' => 'array',
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
