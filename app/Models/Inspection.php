<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One inspection session for a uniform checklist form (workplace, signage, fire
 * alarm, …) in a unit/month, with its checklist result rows. The generic engine
 * that backs every uniform K3 inspection form.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string $form_code
 * @property Carbon|null $inspection_date
 * @property string|null $inspector_team
 * @property string|null $ketua_tim
 */
#[Fillable([
    'unit_id', 'year', 'month', 'form_code', 'inspection_date',
    'inspector_team', 'ketua_tim', 'keterangan', 'input_by',
])]
class Inspection extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<InspectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
        ];
    }

    /**
     * @return HasMany<InspectionResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }
}
