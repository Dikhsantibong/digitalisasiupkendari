<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One Akun Unit per generating unit — every divisi, only that unit's data —
 * e-mail unit.{kode-unit}@upkendari.co.id (e.g. unit.pltd-poasia@…).
 *
 * Idempotent: re-running keeps an account's password and re-applies the role
 * assignment. New accounts start with the password "password", to be changed
 * on first use.
 */
class UnitAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    public const DOMAIN = 'upkendari.co.id';

    private const PASSWORD = 'password';

    public function run(): void
    {
        foreach (Unit::query()->orderBy('id')->get() as $unit) {
            $user = User::query()->firstOrNew(['email' => self::emailFor($unit)]);
            $user->fill([
                'name' => "Akun {$unit->name}",
                'position' => RoleName::AkunUnit->label(),
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
            if (! $user->exists) {
                $user->employee_id = 'UNIT-'.strtoupper(Str::random(8));
                $user->password = Hash::make(self::PASSWORD);
            }
            $user->save();

            $user->assignRole(RoleName::AkunUnit, $unit);
        }
    }

    public static function emailFor(Unit $unit): string
    {
        return 'unit.'.(Str::slug($unit->code ?? $unit->name ?? (string) $unit->id) ?: 'unit').'@'.self::DOMAIN;
    }
}
