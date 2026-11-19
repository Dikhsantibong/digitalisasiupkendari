<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\ServiceUnit;
use App\Models\User;
use App\Support\AccountEmail;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One Admin UL account per service unit (user, 2026-11-18) — every divisi of
 * every unit under the UL plus the Laporan UL — e-mail
 * a short admin.{UL}@upkendari.co.id (e.g. admin.poasia@…, AccountEmail).
 *
 * Idempotent: re-running keeps an account's password and re-applies the role
 * assignment (run again after adding a UL). New accounts start with the
 * password "password", to be changed on first use.
 */
class AdminUlAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PASSWORD = 'password';

    public function run(): void
    {
        foreach (ServiceUnit::query()->orderBy('id')->get() as $serviceUnit) {
            $user = User::query()->firstOrNew(['email' => self::emailFor($serviceUnit)]);
            $user->fill([
                'name' => "Admin {$serviceUnit->name}",
                'position' => RoleName::AdminUl->label(),
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            if (! $user->exists) {
                $user->employee_id = 'ADMINUL-'.strtoupper(Str::random(8));
                $user->password = Hash::make(self::PASSWORD);
            }

            $user->save();
            $user->assignRole(RoleName::AdminUl, $serviceUnit);
        }
    }

    public static function emailFor(ServiceUnit $serviceUnit): string
    {
        // Short login e-mail, e.g. admin.poasia@upkendari.co.id (AccountEmail).
        return AccountEmail::short('admin.'.(Str::slug($serviceUnit->name ?? $serviceUnit->code ?? (string) $serviceUnit->id) ?: 'ul').'@'.UnitAccountSeeder::DOMAIN);
    }
}
