<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Jadwal Pembuatan Data Teknis Pembangkit (OPERASI) — matriks tahunan 12 bulan.
 */
#[Fillable(['unit_id', 'year', 'no_urut', 'section', 'nama', 'pic', 'months', 'sort_order', 'input_by'])]
class OperasiDataTeknis extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_data_tekniss';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unit_id' => 'integer', 'year' => 'integer', 'no_urut' => 'integer', 'months' => 'array', 'sort_order' => 'integer'];
    }
}
