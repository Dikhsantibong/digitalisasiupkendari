<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Foto eviden satu hari & shift di Program 5S 5R Pemeliharaan (disk public).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $day
 * @property string $shift
 * @property Carbon|null $tanggal
 * @property int $minggu
 * @property string $path
 * @property int $sort_order
 * @property int|null $input_by
 */
#[Fillable(['unit_id', 'year', 'month', 'day', 'shift', 'tanggal', 'minggu', 'path', 'sort_order', 'input_by'])]
class HarProgram5s5rEvidence extends Model
{
    use BelongsToUnit;

    protected $table = 'har_program_5s5r_evidences';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'day' => 'integer',
            'tanggal' => 'date',
            'minggu' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
