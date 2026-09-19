<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use App\Support\LogistikForms\LogistikForms;
use Database\Factories\LogistikFormRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row of a Logistik & Gudang table form ({@see LogistikForms}).
 *
 * @property int $id
 * @property int $unit_id
 * @property string $form
 * @property int $year
 * @property int $month
 * @property string|null $section
 * @property array<string, mixed> $data column key => value
 * @property int $sort_order
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'form', 'year', 'month', 'section', 'data', 'sort_order', 'input_by'])]
class LogistikFormRow extends Model
{
    /** @use HasFactory<LogistikFormRowFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'data' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
