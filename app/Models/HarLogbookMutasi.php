<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Logbook Mutasi Harian Tim Pemeliharaan: satu per unit per tanggal.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property Carbon $tanggal
 * @property list<array{nama: string, jabatan: string|null, keterangan: string|null, paraf: string|null}> $absensi
 * @property list<array{item: string, keterangan: string|null}> $apd
 * @property list<array{uraian: string, keterangan: string|null}> $rutin
 * @property list<array{uraian: string, keterangan: string|null}> $non_rutin
 * @property list<array{uraian: string, keterangan: string|null}> $kondisi_k3
 */
#[Fillable(['unit_id', 'year', 'month', 'tanggal', 'absensi', 'apd', 'rutin', 'non_rutin', 'kondisi_k3', 'input_by'])]
class HarLogbookMutasi extends Model
{
    use BelongsToUnit;

    protected $table = 'har_logbook_mutasis';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'tanggal' => 'date',
            'absensi' => 'array',
            'apd' => 'array',
            'rutin' => 'array',
            'non_rutin' => 'array',
            'kondisi_k3' => 'array',
        ];
    }
}
