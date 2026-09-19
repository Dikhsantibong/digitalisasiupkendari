<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\PdmPermitToWorkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One permit of the PdM "Laporan Permit to Work (PTW) Pembangkit".
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $no_urut
 * @property string|null $uraian
 * @property Carbon|null $tanggal
 * @property string $status open|close
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'year', 'month', 'no_urut', 'uraian', 'tanggal', 'status', 'input_by'])]
class PdmPermitToWork extends Model
{
    /** @use HasFactory<PdmPermitToWorkFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'pdm_permit_to_works';

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
            'tanggal' => 'date:Y-m-d',
        ];
    }
}
