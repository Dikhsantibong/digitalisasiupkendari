<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Laporan Kondisi Abnormal dan Gangguan Pembangkit (Modul OPERASI).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $no_urut
 * @property string|null $uraian_kondisi
 * @property Carbon|null $tanggal
 * @property int $is_abnormal
 * @property string $durasi_abnormal
 * @property int $is_gangguan
 * @property string $durasi_gangguan
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
    'uraian_kondisi',
    'tanggal',
    'is_abnormal',
    'durasi_abnormal',
    'is_gangguan',
    'durasi_gangguan',
    'input_by',
])]
class KondisiAbnormal extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'kondisi_abnormals';

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
            'tanggal' => 'date:Y-m-d',
            'is_abnormal' => 'integer',
            'durasi_abnormal' => 'decimal:2',
            'is_gangguan' => 'integer',
            'durasi_gangguan' => 'decimal:2',
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
