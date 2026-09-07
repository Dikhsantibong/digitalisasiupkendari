<?php

namespace App\Models;

use App\Enums\BeritaAcaraType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Berita Acara template setting. A null {@see $unit_id} is the global default;
 * a per-unit row overrides it. The letter number here is fixed, not generated.
 *
 * @property int $id
 * @property string $module_code
 * @property BeritaAcaraType $type
 * @property int|null $unit_id
 * @property string $title
 * @property string $document_number
 * @property string $revision
 * @property Carbon|null $revision_date
 * @property-read Unit|null $unit
 */
#[Fillable(['module_code', 'type', 'unit_id', 'title', 'document_number', 'revision', 'revision_date'])]
class DocumentTemplate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BeritaAcaraType::class,
            'revision_date' => 'date',
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
