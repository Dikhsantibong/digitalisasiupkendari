<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jadwal First Line Maintenance (FLM) Operator per unit, bulan, dan tahun.
 * Tiap baris = satu urutan (RUTIN/NON RUTIN) per shift dengan peta nilai harian.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string $section
 * @property string|null $shift
 * @property string $label
 * @property string $row_type
 * @property array<string, string>|null $days
 * @property int $target
 * @property string|null $keterangan
 * @property int $sort_order
 * @property int|null $input_by
 */
#[Fillable([
    'unit_id', 'year', 'month', 'section', 'shift', 'label', 'row_type',
    'days', 'target', 'keterangan', 'sort_order', 'input_by',
])]
class OperasiFlmJadwal extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_flm_jadwals';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'days' => 'array',
            'target' => 'integer',
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
