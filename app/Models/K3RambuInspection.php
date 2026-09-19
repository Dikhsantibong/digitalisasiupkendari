<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inspeksi Rambu-Rambu K3 & B3 per unit, bulan, dan tahun.
 */
#[Fillable([
    'unit_id', 'year', 'month', 'no_urut', 'rambu', 'lokasi',
    'kondisi', 'keterangan', 'sort_order', 'input_by',
])]
class K3RambuInspection extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'k3_rambu_inspections';

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
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
