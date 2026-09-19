<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use App\Support\LogistikJadwal;
use Database\Factories\LogistikJadwalRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row of a Logistik & Gudang jadwal sheet ({@see LogistikJadwal}).
 *
 * @property int $id
 * @property int $unit_id
 * @property string $jadwal sheet key, see LogistikJadwal::SHEETS
 * @property int $year
 * @property int $month
 * @property string|null $section
 * @property string|null $nama
 * @property string|null $pic
 * @property array<string, string>|null $days day of month => code
 * @property int|null $target
 * @property string|null $keterangan
 * @property list<string>|null $evidence photo paths (public disk)
 * @property int $sort_order
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'jadwal', 'year', 'month', 'section', 'nama', 'pic', 'days', 'target', 'keterangan', 'evidence', 'sort_order', 'input_by'])]
class LogistikJadwalRow extends Model
{
    /** @use HasFactory<LogistikJadwalRowFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'days' => 'array',
            'evidence' => 'array',
            'target' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
