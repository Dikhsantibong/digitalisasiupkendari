<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit trail of a report workflow: who (and in which jabatan) did what,
 * the status before & after, when, and the note given.
 *
 * @property int $id
 * @property int $report_workflow_id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $jabatan
 * @property string $action
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $note
 * @property Carbon|null $created_at
 */
#[Fillable(['report_workflow_id', 'user_id', 'user_name', 'jabatan', 'action', 'from_status', 'to_status', 'note', 'created_at'])]
class ReportWorkflowLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
