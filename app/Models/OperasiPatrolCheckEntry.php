<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One shift-level inspection entry for checklist-style Patrol Check Mesin
 * (e.g. PLTD Poasia POA-OPR-CHK-01, minimum 2 checks per shift).
 *
 * @property int $id
 * @property int $patrol_check_mesin_id
 * @property Carbon $tanggal
 * @property string $shift
 * @property string $jam
 * @property string $operator_name
 * @property list<array<string, mixed>> $items
 * @property string|null $catatan
 * @property int|null $input_by
 */
class OperasiPatrolCheckEntry extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'items' => 'array',
        ];
    }

    /**
     * @return BelongsTo<OperasiPatrolCheckMesin, $this>
     */
    public function patrolCheckMesin(): BelongsTo
    {
        return $this->belongsTo(OperasiPatrolCheckMesin::class, 'patrol_check_mesin_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
