<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\PdmFormDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A saved generic PdM input form (see App\Support\PdmForms) for one unit,
 * period and — for per-machine forms — subject.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $form
 * @property int $year
 * @property int $month
 * @property string $subject
 * @property array<string, mixed>|null $header
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Collection<int, PdmFormItem> $items
 */
#[Fillable(['unit_id', 'form', 'year', 'month', 'subject', 'header', 'input_by'])]
class PdmFormDocument extends Model
{
    /** @use HasFactory<PdmFormDocumentFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'pdm_form_documents';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'header' => 'array',
        ];
    }

    /**
     * @return HasMany<PdmFormItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PdmFormItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
