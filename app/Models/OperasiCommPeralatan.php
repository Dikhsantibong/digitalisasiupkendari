<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Pelaksanaan Commissioning Test Peralatan Pembangkit.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int|null $no_urut
 * @property string $nama_peralatan
 * @property array<string>|null $beban_50
 * @property array<string>|null $beban_75
 * @property array<string>|null $beban_100
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
    'nama_peralatan',
    'beban_50',
    'beban_75',
    'beban_100',
    'keterangan',
    'sort_order',
    'input_by',
])]
class OperasiCommPeralatan extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_comm_peralatans';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'no_urut' => 'integer',
            'beban_50' => 'array',
            'beban_75' => 'array',
            'beban_100' => 'array',
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
