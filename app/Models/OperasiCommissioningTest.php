<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Pelaksanaan Commissioning Test Mesin Pembangkit.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string $section
 * @property int|null $no_urut
 * @property string $kegiatan
 * @property string|null $status
 * @property string|null $pic
 * @property string|null $paraf
 * @property string|null $catatan
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
    'section',
    'no_urut',
    'kegiatan',
    'status',
    'pic',
    'paraf',
    'catatan',
    'sort_order',
    'input_by',
])]
class OperasiCommissioningTest extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_commissioning_tests';

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
