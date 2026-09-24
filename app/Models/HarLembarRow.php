<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu item lembar matriks HAR (definisi: App\Support\HarLembar).
 *
 * @property int $id
 * @property int $unit_id
 * @property string $lembar
 * @property int $year
 * @property int $month
 * @property string $subject
 * @property string|null $section
 * @property int $sort_order
 * @property array<string, string|int|float|null> $fields
 * @property array<string, array<string, string>> $cells
 */
#[Fillable(['unit_id', 'lembar', 'year', 'month', 'subject', 'section', 'sort_order', 'fields', 'cells', 'input_by'])]
class HarLembarRow extends Model
{
    use BelongsToUnit;

    protected $table = 'har_lembar_rows';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'sort_order' => 'integer',
            'fields' => 'array',
            'cells' => 'array',
        ];
    }
}
