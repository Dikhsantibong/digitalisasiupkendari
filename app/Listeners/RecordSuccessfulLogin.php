<?php

namespace App\Listeners;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Impersonation;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

/**
 * Stamps the account with its last sign in and records the event so Super
 * Admins can see who has been using the system.
 */
class RecordSuccessfulLogin
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        // A Super Admin switching accounts (Pindah Akun) is not a sign in of that user.
        if (! $user instanceof User || (Request::hasSession() && Request::session()->get(Impersonation::SWITCHING_KEY))) {
            return;
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => Request::ip(),
        ])->save();

        $this->activityLogger->log(
            ActivityEvent::LoggedIn,
            "{$user->name} masuk ke aplikasi",
            $user,
        );
    }
}
