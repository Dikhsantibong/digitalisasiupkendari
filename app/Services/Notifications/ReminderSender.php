<?php

namespace App\Services\Notifications;

use App\Models\ReminderLog;
use App\Models\User;
use App\Notifications\ReminderNotification;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The one door every reminder goes through: it honours the role permission and
 * the user's own settings, and sends each reminder key at most once per user.
 */
class ReminderSender
{
    /**
     * Send the reminder unless the user opted out or already received it.
     * Returns whether it was sent.
     */
    public function send(User $user, ReminderNotification $reminder): bool
    {
        if (! $user->wantsNotification($reminder->category)) {
            return false;
        }

        $claimed = ReminderLog::query()->insertOrIgnore([
            'user_id' => $user->id,
            'key' => $reminder->key,
            'created_at' => Carbon::now(),
        ]);

        if ($claimed === 0) {
            return false;
        }

        try {
            $user->notify($reminder);
        } catch (Throwable $e) {
            // The bell copy is stored first; a failing push service must not stop the other reminders.
            report($e);
        }

        return true;
    }
}
