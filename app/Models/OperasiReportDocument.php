<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved, editable Operasi report document (rich text or spreadsheet grid)
 * with the numbers snapshot it was generated from. One per unit + report +
 * engine + period.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $report_code
 * @property int|null $engine_id
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
    'unit_id', 'report_code', 'engine_id', 'month', 'year', 'document_number',
    'content_html', 'content_grid', 'format', 'content_version', 'snapshot', 'created_by',
])]
class OperasiReportDocument extends Model
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
