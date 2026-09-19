<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Pemeliharaan P0 - P5 per unit, mesin, bulan, dan tahun.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $machine_id
 * @property int $year
 * @property int $month
 * @property array<string, string|null>|null $rencana
 * @property array<string, string|null>|null $realisasi
 * @property array<string, mixed>|null $durasi
 * @property array<string, mixed>|null $warna
 * @property string|null $operating_hours
 * @property string|null $keterangan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Machine $machine
 * @property-read User|null $inputUser
 */
#[Fillable([
    'unit_id',
    'machine_id',
    'year',
    'month',
    'rencana',
    'realisasi',
    'durasi',
    'warna',
    'operating_hours',
    'keterangan',
    'input_by',
])]
class HarJadwalP0P5 extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'har_jadwal_p0_p5s';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'rencana' => 'array',
            'realisasi' => 'array',
            'durasi' => 'array',
            'warna' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
