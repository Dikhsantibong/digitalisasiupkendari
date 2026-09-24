<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris tabel input bebas HAR (definisi: App\Support\HarTabel).
 *
 * @property int $id
 * @property int $unit_id
 * @property string $tabel
 * @property int $year
 * @property int $month
 * @property int $sort_order
 * @property array<string, string|int|float|null> $data
 */
#[Fillable(['unit_id', 'tabel', 'year', 'month', 'sort_order', 'data', 'input_by'])]
class HarTabelRow extends Model
{
    use BelongsToUnit;

    protected $table = 'har_tabel_rows';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'sort_order' => 'integer',
            'data' => 'array',
        ];
    }
}
