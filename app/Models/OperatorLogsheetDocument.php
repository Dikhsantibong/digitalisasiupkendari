<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A saved, editable Operator logsheet report document (rich text or spreadsheet
 * grid) for one machine on one day.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $engine_id
 * @property Carbon $log_date
 * @property string|null $document_number
 * @property string $format
 * @property int|null $content_version
 * @property string|null $content_html
 * @property array<string, mixed>|null $content_grid
 * @property array<string, mixed>|null $snapshot
 * @property int|null $created_by
 * @property-read Unit $unit
 */
#[Fillable([
    'unit_id', 'engine_id', 'log_date', 'document_number', 'format',
    'content_version', 'content_html', 'content_grid', 'snapshot', 'created_by',
])]
class OperatorLogsheetDocument extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'content_grid' => 'array',
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function engine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'engine_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
