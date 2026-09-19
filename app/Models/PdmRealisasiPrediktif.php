<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\PdmRealisasiPrediktifFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One activity of the PdM "Realisasi Pemeliharaan Prediktif Bulanan".
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $no_urut
 * @property string $uraian
 * @property string|null $mesin
 * @property list<int>|null $rencana
 * @property list<int>|null $realisasi
 * @property string|null $durasi
 * @property string|null $keterangan
 * @property int $sort_order
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['unit_id', 'year', 'month', 'no_urut', 'uraian', 'mesin', 'rencana', 'realisasi', 'durasi', 'keterangan', 'sort_order', 'input_by'])]
class PdmRealisasiPrediktif extends Model
{
    /** @use HasFactory<PdmRealisasiPrediktifFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'pdm_realisasi_prediktifs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'no_urut' => 'integer',
            'rencana' => 'array',
            'realisasi' => 'array',
            'durasi' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
