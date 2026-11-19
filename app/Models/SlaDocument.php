<?php

namespace App\Models;

use App\Services\Reports\SlaDocumentBuilder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An SLA document edited & saved in the document editor
 * ({@see SlaDocumentBuilder}): one divisi's SLA (scope = divisi key) or the
 * Laporan SLA gabungan (scope = "gabungan") of one unit — or every unit
 * (unit_id null) — for one month.
 *
 * @property int $id
 * @property string $scope
 * @property int|null $unit_id
 * @property int $month
 * @property int $year
 * @property string $format html|grid
 * @property string|null $content_html
 * @property array<string, mixed>|null $content_grid
 * @property int $content_version
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit|null $unit
 */
#[Fillable(['scope', 'unit_id', 'month', 'year', 'format', 'content_html', 'content_grid', 'content_version', 'created_by', 'updated_by'])]
class SlaDocument extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'month' => 'integer',
            'year' => 'integer',
            'content_grid' => 'array',
            'content_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
