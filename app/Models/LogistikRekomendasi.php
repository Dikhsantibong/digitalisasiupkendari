<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\LogistikRekomendasiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row of the Logistik input "Rekomendasi Logistik & Gudang".
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $no_urut
 * @property string|null $uraian
 * @property string|null $kondisi_existing
 * @property string|null $tindak_lanjut
 * @property string|null $keterangan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'year', 'month', 'no_urut', 'uraian', 'kondisi_existing', 'tindak_lanjut', 'keterangan', 'input_by'])]
class LogistikRekomendasi extends Model
{
    /** @use HasFactory<LogistikRekomendasiFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'logistik_rekomendasis';

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
        ];
    }
}
