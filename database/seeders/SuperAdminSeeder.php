<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Creates the account that controls the system.
 *
 * Credentials may be overridden with SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD;
 * the password is only applied when the account is first created, so re-running
 * the seeder never resets a password that has been changed.
 */
class SuperAdminSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $email = (string) env('SUPER_ADMIN_EMAIL', 'admin@gmail.com');

        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->password = (string) env('SUPER_ADMIN_PASSWORD', 'password');
        }

        $user->fill([
            'name' => (string) env('SUPER_ADMIN_NAME', 'Super Admin'),
            'position' => 'Super Administrator',
            'is_active' => true,
        ]);

        $user->email_verified_at ??= now();
        $user->save();

        $user->assignRole(RoleName::SuperAdmin);
    }
}
