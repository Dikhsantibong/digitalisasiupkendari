<?php

namespace App\Models;

use App\Enums\PersediaanJenis;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\OperasiPersediaanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A monthly stock sheet of a unit (Persediaan Bahan Bakar or Pelumas). Only
 * what is typed in is stored: a corrected opening stock per item and the
 * daily penerimaan / pengiriman. PEMAKAIAN comes from the Pemakaian sheet of
 * the month; `closing` is the computed saldo akhir per item at save time.
 *
 * @property int $id
 * @property int $unit_id
 * @property PersediaanJenis $jenis
 * @property int $month
 * @property int $year
 * @property array<string, float>|null $opening
 * @property array<string, array<int|string, array<string, float>>>|null $entries
 * @property array<string, float>|null $closing
 * @property string|null $catatan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'jenis', 'month', 'year', 'opening', 'entries', 'closing', 'catatan', 'input_by'])]
class OperasiPersediaan extends Model
{
    /** @use HasFactory<OperasiPersediaanFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_persediaan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => PersediaanJenis::class,
            'month' => 'integer',
            'year' => 'integer',
            'opening' => 'array',
            'entries' => 'array',
            'closing' => 'array',
        ];
    }
}
