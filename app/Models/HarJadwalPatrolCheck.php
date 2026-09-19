<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Piket Patrol Check Harian per operator, unit, bulan, dan tahun.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $employee_id
 * @property int $year
 * @property int $month
 * @property array<string, string|int|null>|null $rencana
 * @property array<string, string|int|null>|null $realisasi
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Employee $employee
 * @property-read User|null $inputUser
 */
#[Fillable([
    'unit_id',
    'employee_id',
    'year',
    'month',
    'rencana',
    'realisasi',
    'input_by',
])]
class HarJadwalPatrolCheck extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'har_jadwal_patrol_checks';

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
