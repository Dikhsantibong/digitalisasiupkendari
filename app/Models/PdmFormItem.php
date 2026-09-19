<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A row of a generic PdM form document.
 *
 * @property int $id
 * @property int $pdm_form_document_id
 * @property int $unit_id
 * @property string $section
 * @property array<string, string|null> $data
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['pdm_form_document_id', 'unit_id', 'section', 'data', 'sort_order'])]
class PdmFormItem extends Model
{
    use BelongsToUnit;

    protected $table = 'pdm_form_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'data' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
