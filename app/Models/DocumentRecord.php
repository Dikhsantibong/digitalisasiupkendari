<?php

namespace App\Models;

use App\Enums\BeritaAcaraType;
use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A saved Berita Acara with its numbers snapshot.
 *
 * @property int $id
 * @property int $unit_id
 * @property BeritaAcaraType $type
 * @property int $month
 * @property int $year
 * @property int|null $report_period_id
 * @property string $document_number
 * @property array<string, mixed> $snapshot
 * @property string|null $pdf_path
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property-read Unit $unit
 */
#[Fillable([
    'unit_id', 'type', 'month', 'year', 'report_period_id', 'document_number',
    'content_html', 'content_grid', 'format', 'snapshot', 'pdf_path',
    'created_by', 'approved_by', 'approved_at',
])]
class DocumentRecord extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BeritaAcaraType::class,
            'snapshot' => 'array',
            'content_grid' => 'array',
            'approved_at' => 'datetime',
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
