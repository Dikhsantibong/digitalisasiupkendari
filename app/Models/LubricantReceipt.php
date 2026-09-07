<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\LubricantReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A lubricant delivery (manual input).
 *
 * @property int $id
 * @property int $unit_id
 * @property Carbon $report_date
 * @property int $lubricant_type_id
 * @property string $volume
 * @property string|null $do_number
 * @property int|null $input_by
 * @property-read Unit $unit
 * @property-read LubricantType $lubricantType
 */
#[Fillable(['unit_id', 'report_date', 'lubricant_type_id', 'volume', 'do_number', 'input_by'])]
class LubricantReceipt extends Model
{
    /** @use HasFactory<LubricantReceiptFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<LubricantType, $this>
     */
    public function lubricantType(): BelongsTo
    {
        return $this->belongsTo(LubricantType::class);
    }
}
