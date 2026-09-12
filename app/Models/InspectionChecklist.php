<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\InspectionChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A checklist item for a generic inspection form (workplace, signage, …) per
 * unit. Grouped by form_code; drives the reusable inspections engine.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $form_code
 * @property string $item_text
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['unit_id', 'form_code', 'item_text', 'sort_order', 'is_active'])]
class InspectionChecklist extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<InspectionChecklistFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
