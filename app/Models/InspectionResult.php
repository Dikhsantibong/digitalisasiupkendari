<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One checklist result row within an {@see Inspection}.
 *
 * @property int $id
 * @property int $inspection_id
 * @property string $item_ref
 * @property string|null $kondisi
 * @property string|null $tindak_lanjut
 * @property string|null $nilai
 * @property string|null $catatan
 * @property int $sort_order
 */
#[Fillable(['inspection_id', 'item_ref', 'kondisi', 'tindak_lanjut', 'nilai', 'catatan', 'sort_order'])]
class InspectionResult extends Model
{
    /**
     * @return BelongsTo<Inspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }
}
