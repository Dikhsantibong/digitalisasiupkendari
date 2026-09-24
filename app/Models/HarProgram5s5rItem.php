<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\HarProgram5s5rItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu program (Ringkas, Rapi, Resik, Rawat, Rajin) pada satu minggu di
 * Jadwal Program 5S 5R Pemeliharaan (Modul HAR). Definisi: App\Support\HarProgram5s5r.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $minggu
 * @property string $program
 * @property string|null $detail
 * @property string|null $pic
 * @property string|null $kondisi_awal
 * @property bool $membersihkan
 * @property bool $merapikan
 * @property bool $membuang_sampah
 * @property bool $mengecat
 * @property bool $lainnya
 * @property string|null $progres
 * @property string|null $kondisi_akhir
 * @property int|null $jumlah
 * @property string|null $keterangan
 * @property int|null $input_by
 */
#[Fillable([
    'unit_id',
    'year',
    'month',
    'minggu',
    'program',
    'detail',
    'pic',
    'kondisi_awal',
    'membersihkan',
    'merapikan',
    'membuang_sampah',
    'mengecat',
    'lainnya',
    'progres',
    'kondisi_akhir',
    'jumlah',
    'keterangan',
    'input_by',
])]
class HarProgram5s5rItem extends Model
{
    /** @use HasFactory<HarProgram5s5rItemFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'har_program_5s5r_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'minggu' => 'integer',
            'membersihkan' => 'boolean',
            'merapikan' => 'boolean',
            'membuang_sampah' => 'boolean',
            'mengecat' => 'boolean',
            'lainnya' => 'boolean',
            'jumlah' => 'integer',
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
