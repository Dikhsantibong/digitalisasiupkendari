<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Laporan Unsafe Action dan Unsafe Condition (Modul HAR).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string $periode
 * @property string $kategori
 * @property string $temuan
 * @property string|null $kondisi
 * @property string|null $tindak_lanjut
 * @property string|null $rekomendasi
 * @property string|null $lokasi
 * @property string $keterangan
 * @property string|null $foto_sebelum
 * @property string|null $foto_sesudah
 * @property int $sort_order
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read User|null $inputUser
 */
#[Fillable([
    'unit_id',
    'year',
    'month',
    'periode',
    'kategori',
    'temuan',
    'kondisi',
    'tindak_lanjut',
    'rekomendasi',
    'lokasi',
    'keterangan',
    'foto_sebelum',
    'foto_sesudah',
    'sort_order',
    'input_by',
])]
class HarUnsafeCondition extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'har_unsafe_conditions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
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
