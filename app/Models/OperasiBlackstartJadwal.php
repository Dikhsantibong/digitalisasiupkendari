<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Pemeriksaan Instalasi Blackstart (OPERASI).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int|null $no_urut
 * @property string $uraian
 * @property string|null $pic
 * @property array<string>|null $rencana
 * @property array<string>|null $realisasi
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
    'uraian',
    'pic',
    'rencana',
    'realisasi',
    'keterangan',
    'sort_order',
    'input_by',
])]
class OperasiBlackstartJadwal extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_blackstart_jadwals';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'no_urut' => 'integer',
            'rencana' => 'array',
            'realisasi' => 'array',
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
