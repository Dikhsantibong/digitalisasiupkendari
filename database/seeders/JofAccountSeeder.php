<?php

namespace Database\Seeders;

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\User;
use App\Support\AccountEmail;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One JOF Administrasi Umum account per UL (user, 2026-11-20) — the
 * Administrasi & Keuangan of the UL: jof.{UL}@upkendari.co.id (e.g.
 * jof.poasia@…), with its employee (jabatan JOF Administrasi Umum of the UL,
 * the "Dibuat Oleh" of the Laporan Realisasi Cash Card).
 *
 * Idempotent: an existing account keeps its password, an existing holder of
 * the jabatan is linked instead of a new one. New accounts start with the
 * password "password", to be changed on first use.
 */
class JofAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PASSWORD = 'password';

    public function run(): void
    {
        foreach (ServiceUnit::query()->orderBy('id')->get() as $serviceUnit) {
            $user = User::query()->firstOrNew(['email' => self::emailFor($serviceUnit)]);
            $user->fill([
                'name' => "JOF Administrasi Umum {$serviceUnit->name}",
                'position' => EmployeePosition::JofAdministrasiUmum->value,
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            if (! $user->exists) {
                $user->employee_id = 'JOF-'.strtoupper(Str::random(8));
                $user->password = Hash::make(self::PASSWORD);
            }

            $user->save();
            $user->assignRole(RoleName::JofAdministrasiUmum, $serviceUnit);

            $key = EmployeePosition::JofAdministrasiUmum->singletonKey(null, $serviceUnit->id);
            $holder = Employee::query()->where('singleton_key', $key)->first();
            if ($holder === null) {
                Employee::query()->create([
                    'service_unit_id' => $serviceUnit->id,
                    'unit_id' => null,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'position' => EmployeePosition::JofAdministrasiUmum->value,
                    'is_active' => true,
                ]);
            } elseif ($holder->user_id === null && ! Employee::query()->where('user_id', $user->id)->exists()) {
                $holder->forceFill(['user_id' => $user->id])->save();
            }
        }
    }

    public static function emailFor(ServiceUnit $serviceUnit): string
    {
        return AccountEmail::short('jof.'.(Str::slug($serviceUnit->name ?? $serviceUnit->code ?? (string) $serviceUnit->id) ?: 'ul').'@'.AccountEmail::DOMAIN);
    }
}
