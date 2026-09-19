<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Jadwal Inventarisasi Tools & Material Operasi — rencana/realisasi harian.
 */
#[Fillable(['unit_id', 'year', 'month', 'no_urut', 'uraian', 'shift', 'rencana', 'realisasi', 'target', 'sort_order', 'input_by'])]
class OperasiInventarisJadwal extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_inventaris_jadwals';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unit_id' => 'integer', 'year' => 'integer', 'month' => 'integer', 'no_urut' => 'integer', 'rencana' => 'array', 'realisasi' => 'array', 'target' => 'integer', 'sort_order' => 'integer'];
    }
}
