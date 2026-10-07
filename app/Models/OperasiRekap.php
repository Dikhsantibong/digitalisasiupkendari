<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The manual corrections of a Pengusahaan Operasi recap (e.g. Kinerja Unit
 * Mesin). A recap is computed from the other sheets; only the values a user
 * overrides are stored here, keyed by row (machine id) then field.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $jenis
 * @property int $month
 * @property int $year
 * @property array<int|string, array<string, mixed>>|null $overrides
 * @property string|null $catatan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'jenis', 'month', 'year', 'overrides', 'catatan', 'input_by'])]
class OperasiRekap extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'overrides' => 'array',
        ];
    }
}
