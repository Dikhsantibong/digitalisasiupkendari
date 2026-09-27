<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\HarLaporanKegiatanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One numbered entry of the Laporan Kegiatan Pemeliharaan (Akses 2 —
 * Pengusahaan) of a unit and report month, for the Mesin or the Listrik &
 * Kontrol report. `groups` lists the machines worked on in the entry.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $category
 * @property int $year
 * @property int $month
 * @property int $sort_order
 * @property Carbon|null $activity_date
 * @property list<array{mesin: string, judul: string, jenis_har: string, uraian: list<string>}> $groups
 * @property string|null $hasil_pekerjaan
 * @property string|null $material_nama
 * @property string|null $material_no_part
 * @property string|null $jumlah
 * @property string|null $data
 * @property string|null $no_lh05
 * @property string|null $no_sr_ba
 * @property string|null $no_tug9
 * @property string|null $no_wo_spki
 */
#[Fillable([
    'unit_id', 'category', 'year', 'month', 'sort_order', 'activity_date', 'groups', 'hasil_pekerjaan',
    'material_nama', 'material_no_part', 'jumlah', 'data', 'no_lh05', 'no_sr_ba', 'no_tug9', 'no_wo_spki', 'input_by',
])]
class HarLaporanKegiatan extends Model
{
    /** @use HasFactory<HarLaporanKegiatanFactory> */
    use BelongsToUnit, HasFactory;

    /** Pemeliharaan mesin (keterangan: No. SR & No. WO). */
    public const MESIN = 'mesin';

    /** Pemeliharaan listrik & kontrol (keterangan: No. BA & No. SPKI). */
    public const LISTRIK = 'listrik';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'groups' => 'array',
        ];
    }
}
