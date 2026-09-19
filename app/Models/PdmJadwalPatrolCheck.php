<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Piket Patrol Check PdM KIT per unit, bulan, dan tahun.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string $kategori
 * @property bool $is_category_header
 * @property string|null $no_urut
 * @property int|null $employee_id
 * @property string $nama
 * @property string|null $no_hp
 * @property int $target
 * @property array<int>|null $jadwal
 * @property string|null $keterangan
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
    'kategori',
    'is_category_header',
    'no_urut',
    'employee_id',
    'nama',
    'no_hp',
    'target',
    'jadwal',
    'keterangan',
    'sort_order',
    'input_by',
])]
class PdmJadwalPatrolCheck extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'pdm_jadwal_patrol_checks';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'is_category_header' => 'boolean',
            'target' => 'integer',
            'jadwal' => 'array',
            'sort_order' => 'integer',
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
