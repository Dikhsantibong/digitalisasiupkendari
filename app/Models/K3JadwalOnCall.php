<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal On Call K3L per unit, bulan, dan tahun (modul K3).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int|null $employee_id
 * @property string $nama
 * @property string|null $kode_prk
 * @property string|null $jabatan
 * @property array<string, string>|null $schedule
 * @property int $total
 * @property float $nilai
 * @property float $rupiah
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
    'kode_prk',
    'jabatan',
    'schedule',
    'total',
    'nilai',
    'rupiah',
    'sort_order',
    'input_by',
])]
class K3JadwalOnCall extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'k3_jadwal_on_calls';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'employee_id' => 'integer',
            'schedule' => 'array',
            'total' => 'integer',
            'nilai' => 'float',
            'rupiah' => 'float',
            'sort_order' => 'integer',
            'input_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
