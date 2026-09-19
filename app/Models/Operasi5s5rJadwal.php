<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Jadwal Pelaksanaan 5S5R (OPERASI) — rencana/realisasi harian per pelaksana.
 */
#[Fillable(['unit_id', 'year', 'month', 'no_urut', 'pelaksana', 'rencana', 'realisasi', 'target', 'sort_order', 'input_by'])]
class Operasi5s5rJadwal extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_5s5r_jadwals';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unit_id' => 'integer', 'year' => 'integer', 'month' => 'integer', 'no_urut' => 'integer', 'rencana' => 'array', 'realisasi' => 'array', 'target' => 'integer', 'sort_order' => 'integer'];
    }
}
