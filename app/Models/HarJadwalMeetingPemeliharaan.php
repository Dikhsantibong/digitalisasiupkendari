<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Meeting Pemeliharaan Pembangkit per unit, bulan, dan tahun.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string $uraian
 * @property int $target
 * @property array<string, string|int|null>|null $rencana
 * @property array<string, string|int|null>|null $realisasi
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
    'uraian',
    'target',
    'rencana',
    'realisasi',
    'keterangan',
    'sort_order',
    'input_by',
])]
class HarJadwalMeetingPemeliharaan extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'har_jadwal_meeting_pemeliharaans';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'target' => 'integer',
            'sort_order' => 'integer',
            'rencana' => 'array',
            'realisasi' => 'array',
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
