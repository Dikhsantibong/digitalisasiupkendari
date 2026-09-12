<?php

namespace App\Models;

use App\Enums\AccidentCategory;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\AccidentReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A K3 accident / occupational-disease report for a unit/month. Most months are
 * NIHIL (is_nihil = true, no casualties).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property Carbon|null $incident_date
 * @property string|null $fungsi
 * @property string|null $lokasi
 * @property AccidentCategory $category
 * @property int $luka_ringan
 * @property int $luka_berat
 * @property int $meninggal
 * @property float|null $kerugian_material
 * @property bool $is_nihil
 */
#[Fillable([
    'unit_id', 'year', 'month', 'incident_date', 'fungsi', 'lokasi', 'category',
    'luka_ringan', 'luka_berat', 'meninggal', 'kerugian_material', 'is_nihil',
    'keterangan', 'input_by',
])]
class AccidentReport extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<AccidentReportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => AccidentCategory::class,
            'incident_date' => 'date',
            'kerugian_material' => 'decimal:2',
            'is_nihil' => 'boolean',
        ];
    }
}
