<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto eviden satu minggu di Jadwal Program 5S 5R Pengoperasian KIT (disk public).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $minggu
 * @property string $path
 * @property int $sort_order
 * @property int|null $input_by
 */
#[Fillable(['unit_id', 'year', 'month', 'minggu', 'path', 'sort_order', 'input_by'])]
class OperasiProgram5s5rEvidence extends Model
{
    use BelongsToUnit;

    protected $table = 'operasi_program_5s5r_evidences';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'minggu' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
