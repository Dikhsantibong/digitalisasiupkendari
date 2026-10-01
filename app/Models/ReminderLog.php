<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Marks a reminder as sent to a user so the scheduler never sends it twice
 * (e.g. "absen-masuk:2026-10-01"). Pruned together with old notifications.
 *
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'key', 'created_at'])]
class ReminderLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
