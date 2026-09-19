<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Laporan Permit to Work (PTW) Pembangkit (Modul OPERASI).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $no_urut
 * @property string|null $uraian
 * @property Carbon|null $tanggal
 * @property string $status
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
    'uraian',
    'tanggal',
    'status',
    'input_by',
])]
class OperasiPermitToWork extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_permit_to_works';

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
