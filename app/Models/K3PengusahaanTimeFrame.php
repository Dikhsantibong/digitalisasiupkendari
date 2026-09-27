<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\K3PengusahaanTimeFrameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Time Frame Kinerja K3 dan Keamanan (Akses 2 — Pengusahaan) per unit, bulan, dan tahun.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int|null $no_urut
 * @property string $uraian_pelaporan
 * @property string|null $pic
 * @property array<int>|null $rencana
 * @property array<int>|null $realisasi
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
    'uraian_pelaporan',
    'pic',
    'rencana',
    'realisasi',
    'keterangan',
    'sort_order',
    'input_by',
])]
class K3PengusahaanTimeFrame extends Model
{
    /** @use HasFactory<K3PengusahaanTimeFrameFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'k3_pengusahaan_time_frames';

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
