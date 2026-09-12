<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved, editable HAR monthly report document (rich text or spreadsheet grid)
 * with the numbers snapshot it was generated from.
 *
 * @property int $id
 * @property int $unit_id
 * @property int|null $report_period_id
 * @property string $type
 * @property int $month
 * @property int $year
 * @property string|null $document_number
 * @property string $format
 * @property string|null $content_html
 * @property array<string, mixed>|null $content_grid
 * @property array<string, mixed>|null $snapshot
 * @property int|null $created_by
 * @property-read Unit $unit
 */
#[Fillable([
    'unit_id', 'report_period_id', 'type', 'month', 'year', 'document_number',
    'content_html', 'content_grid', 'format', 'content_version', 'snapshot', 'created_by',
])]
class HarDocumentRecord extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_grid' => 'array',
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
