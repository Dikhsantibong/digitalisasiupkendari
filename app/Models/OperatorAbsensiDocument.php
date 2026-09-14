<?php

namespace App\Models;

use App\Enums\ScheduleGroupType;
use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved, editable Operator attendance/schedule report document (rich text or
 * spreadsheet grid) for one unit, month, and employee group.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property ScheduleGroupType $group_type
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
    'unit_id', 'year', 'month', 'group_type', 'document_number', 'format',
    'content_version', 'content_html', 'content_grid', 'snapshot', 'created_by',
])]
class OperatorAbsensiDocument extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group_type' => ScheduleGroupType::class,
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
