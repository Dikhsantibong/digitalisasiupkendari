<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\OperatorStartStopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Start Stop Engine: one run of a machine on a tanggal — start, CBG on,
 * CBG off and stop ("HH:MM"), and why it stopped (normal | gangguan; the
 * gangguan stops are Jumlah Kali Gangguan).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $engine_id
 * @property Carbon $tanggal
 * @property string|null $start_time
 * @property string|null $cbg_on
 * @property string|null $cbg_off
 * @property string|null $stop_time
 * @property string $penyebab_stop
 * @property string|null $keterangan
 * @property int|null $input_by
 * @property-read Machine $engine
 * @property-read User|null $inputUser
 */
#[Fillable(['unit_id', 'engine_id', 'tanggal', 'start_time', 'cbg_on', 'cbg_off', 'stop_time', 'penyebab_stop', 'keterangan', 'input_by'])]
class OperatorStartStop extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<OperatorStartStopFactory> */
    use HasFactory;

    public const PENYEBAB = ['normal' => 'Normal', 'gangguan' => 'Gangguan'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'engine_id' => 'integer',
            'tanggal' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function engine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'engine_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
