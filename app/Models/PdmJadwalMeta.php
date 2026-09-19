<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Metadata & penandatangan untuk Jadwal PdM.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $type
 * @property int $year
 * @property int $month
 * @property string|null $doc_number
 * @property string|null $revision
 * @property string|null $effective_date
 * @property string|null $page_number
 * @property int|null $mengetahui_employee_id
 * @property string|null $mengetahui_nama
 * @property string|null $mengetahui_jabatan
 * @property int|null $disetujui_employee_id
 * @property string|null $disetujui_nama
 * @property string|null $disetujui_jabatan
 * @property int|null $dibuat_employee_id
 * @property string|null $dibuat_nama
 * @property string|null $dibuat_jabatan
 * @property string|null $tempat_tanggal
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Employee|null $mengetahuiEmployee
 * @property-read Employee|null $disetujuiEmployee
 * @property-read Employee|null $dibuatEmployee
 */
#[Fillable([
    'unit_id',
    'type',
    'year',
    'month',
    'doc_number',
    'revision',
    'effective_date',
    'page_number',
    'mengetahui_employee_id',
    'mengetahui_nama',
    'mengetahui_jabatan',
    'disetujui_employee_id',
    'disetujui_nama',
    'disetujui_jabatan',
    'dibuat_employee_id',
    'dibuat_nama',
    'dibuat_jabatan',
    'tempat_tanggal',
    'catatan',
])]
class PdmJadwalMeta extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'pdm_jadwal_meta';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'mengetahui_employee_id' => 'integer',
            'disetujui_employee_id' => 'integer',
            'dibuat_employee_id' => 'integer',
        ];
    }

    public function mengetahuiEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'mengetahui_employee_id');
    }

    public function disetujuiEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'disetujui_employee_id');
    }

    public function dibuatEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'dibuat_employee_id');
    }
}
