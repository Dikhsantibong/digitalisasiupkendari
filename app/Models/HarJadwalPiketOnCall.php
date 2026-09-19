<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Kegiatan Piket Pemeliharaan (ON CALL) per personil, unit, bulan, dan tahun.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int|null $employee_id
 * @property string $nama
 * @property string|null $no_hp
 * @property string $kategori
 * @property int $target
 * @property array<string, string|int|null>|null $piket
 * @property int $sort_order
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Employee|null $employee
 * @property-read User|null $inputUser
 */
#[Fillable([
    'unit_id',
    'year',
    'month',
    'employee_id',
    'nama',
    'no_hp',
    'kategori',
    'target',
    'piket',
    'sort_order',
    'input_by',
])]
class HarJadwalPiketOnCall extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'har_jadwal_piket_on_calls';

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
            'piket' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
