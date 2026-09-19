<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inspeksi Hydrant per unit, bulan, dan tahun.
 */
#[Fillable([
    'unit_id', 'year', 'month', 'no_urut', 'lokasi', 'jenis', 'tanggal',
    'hose', 'nozzle', 'box', 'tekanan', 'keterangan', 'foto', 'sort_order', 'input_by',
])]
class K3HydrantInspection extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'k3_hydrant_inspections';

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
