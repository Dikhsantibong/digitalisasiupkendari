<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Catatan (Note) satu dokumen lembar matriks HAR per unit + periode (+ mesin).
 *
 * @property int $id
 * @property int $unit_id
 * @property string $lembar
 * @property int $year
 * @property int $month
 * @property string $subject
 * @property string|null $catatan
 */
#[Fillable(['unit_id', 'lembar', 'year', 'month', 'subject', 'catatan', 'input_by'])]
class HarLembarMeta extends Model
{
    use BelongsToUnit;

    protected $table = 'har_lembar_meta';
}
