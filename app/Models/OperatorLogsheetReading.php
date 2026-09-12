<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reading: the value of one parameter at one time slot within a logsheet.
 *
 * @property int $id
 * @property int $logsheet_id
 * @property string $time_slot
 * @property int $parameter_id
 * @property string|null $value
 * @property string|null $note
 */
#[Fillable(['logsheet_id', 'time_slot', 'parameter_id', 'value', 'note'])]
class OperatorLogsheetReading extends Model
{
    /**
     * @return BelongsTo<OperatorLogsheet, $this>
     */
    public function logsheet(): BelongsTo
    {
        return $this->belongsTo(OperatorLogsheet::class, 'logsheet_id');
    }

    /**
     * @return BelongsTo<LogsheetParameter, $this>
     */
    public function parameter(): BelongsTo
    {
        return $this->belongsTo(LogsheetParameter::class, 'parameter_id');
    }
}
