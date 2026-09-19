<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pemeriksaan Emergency Facility (matriks kesiapan peralatan darurat) per unit,
 * bulan, dan tahun. Dikelompokkan per `grup`.
 */
#[Fillable([
    'unit_id', 'year', 'month', 'grup', 'no_urut', 'nama_peralatan',
    'jml_total', 'jml_ready', 'jml_not_ready', 'lokasi', 'kendala',
    'tindak_lanjut', 'sort_order', 'input_by',
])]
class K3EmergencyFacilityCheck extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'k3_emergency_facility_checks';

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
            'jml_total' => 'integer',
            'jml_ready' => 'integer',
            'jml_not_ready' => 'integer',
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
