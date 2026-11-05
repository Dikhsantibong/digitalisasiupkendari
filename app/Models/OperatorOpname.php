<?php

namespace App\Models;

use App\Enums\OpnameJenis;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\OperatorOpnameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One daily opname sheet (BBM, pelumas or kWh) of a unit — modul Operator.
 *
 * @property int $id
 * @property int $unit_id
 * @property Carbon $tanggal
 * @property OpnameJenis $jenis
 * @property array<string, array<string, array<string, float>>> $data {section: {item: {field: value}}}
 * @property string|null $catatan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read User|null $inputUser
 */
#[Fillable(['unit_id', 'tanggal', 'jenis', 'data', 'catatan', 'input_by'])]
class OperatorOpname extends Model
{
    /** @use HasFactory<OperatorOpnameFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'jenis' => OpnameJenis::class,
            'data' => 'array',
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
