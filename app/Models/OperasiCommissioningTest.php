<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal Pelaksanaan Commissioning Test Mesin Pembangkit.
 *
 * @property int $id
 * @property int $unit_id
 * @property int|null $machine_id
 * @property int $year
 * @property int|null $no_urut
 * @property string $nama_mesin
 * @property string $section
 * @property array<int, string>|null $beban_50
 * @property array<int, string>|null $beban_75
 * @property array<int, string>|null $beban_100
 * @property array<int, string>|null $realisasi
 * @property string|null $keterangan
 * @property int $sort_order
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Machine|null $machine
 * @property-read User|null $inputUser
 */
#[Fillable([
    'unit_id',
    'machine_id',
    'year',
    'no_urut',
    'nama_mesin',
    'section',
    'beban_50',
    'beban_75',
    'beban_100',
    'realisasi',
    'keterangan',
    'sort_order',
    'input_by',
])]
class OperasiCommissioningTest extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_commissioning_tests';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'machine_id' => 'integer',
            'year' => 'integer',
            'no_urut' => 'integer',
            'beban_50' => 'array',
            'beban_75' => 'array',
            'beban_100' => 'array',
            'realisasi' => 'array',
            'sort_order' => 'integer',
            'input_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /**
     * Prefer the live Master Data Mesin name when linked, falling back to stored text.
     */
    public function displayMachineName(): string
    {
        return $this->machine ? mb_strtoupper($this->machine->name) : (string) $this->nama_mesin;
    }
}
