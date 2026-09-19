<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\PdmDocumentRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved, editable Laporan PdM & Maturity Level Pembangkit (rich text or
 * spreadsheet grid) with the snapshot it was generated from. Mirrors
 * {@see K3DocumentRecord}.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $type
 * @property int $month
 * @property int $year
 * @property string|null $document_number
 * @property string $format
 * @property string|null $content_html
 * @property array<string, mixed>|null $content_grid
 * @property int $content_version
 * @property array<string, mixed>|null $snapshot
 * @property int|null $created_by
 */
#[Fillable([
    'unit_id', 'type', 'month', 'year', 'document_number',
    'content_html', 'content_grid', 'format', 'content_version', 'snapshot', 'created_by',
])]
class PdmDocumentRecord extends Model
{
    /** @use HasFactory<PdmDocumentRecordFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'content_version' => 'integer',
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
