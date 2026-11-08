<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The real staff of one unit under its Project Leader (the TL are entered by
 * hand). Each roster member is keyed by its NIP: existing placeholders of
 * {@see EmployeeSeeder} get the real name, the missing ones are added, and
 * every person gets a login account linked through employees.user_id.
 *
 * Placeholder accounts replaced by a personal one (the demo operators and the
 * Harmes/Harlist without a person) are deactivated, never deleted; report
 * signer jabatan without a name are left as they are so no signature block
 * goes missing.
 *
 * Idempotent: re-running updates the same rows and accounts. New accounts use
 * the password "password". Model events run so division and singleton_key are
 * derived by {@see Employee}.
 */
abstract class UnitStaffSeeder extends Seeder
{
    /** Code of the unit this roster belongs to. */
    public const UNIT_CODE = '';

    /**
     * NIP suffix => person. `account` is the e-mail prefix of an account that
     * already exists for the jabatan (it keeps its role and only takes the
     * name); without it a personal account with `role` is created.
     *
     * @var array<int, array{name: string, position: string, regu?: string, leader?: bool, division?: string, role?: RoleName, account?: string}>
     */
    public const STAFF = [];

    /** NIP suffixes of placeholder employees without a person. */
    public const INACTIVE_EMPLOYEES = [];

    /** E-mail prefixes of placeholder accounts replaced by a personal account. */
    public const INACTIVE_ACCOUNTS = [];

    private const PASSWORD = 'password';

    private const DOMAIN = 'upkendari.co.id';

    public function run(): void
    {
        $unit = Unit::query()->where('code', static::UNIT_CODE)->first();

        if ($unit === null) {
            $this->command?->warn('Unit '.static::UNIT_CODE.' tidak ditemukan, tidak ada yang diubah.');

            return;
        }

        $slug = Str::slug($unit->code);

        foreach (static::STAFF as $suffix => $person) {
            $nip = EmployeeSeeder::nip($unit, $suffix);
            $employee = Employee::query()->firstOrNew(['nip' => $nip]);
            $employee->fill([
                'unit_id' => $unit->id,
                'service_unit_id' => null,
                'name' => $person['name'],
                'position' => $person['position'],
                'regu' => $person['regu'] ?? null,
                'is_shift_leader' => $person['leader'] ?? false,
                'division' => $person['division'] ?? $employee->division,
                'is_active' => true,
            ]);

            $user = isset($person['account'])
                ? User::query()->where('email', "{$person['account']}.{$slug}@".self::DOMAIN)->first()
                : $this->personalAccount($person, $nip, $slug, $unit);

            if ($user !== null) {
                $user->forceFill(['name' => $person['name'], 'is_active' => true])->save();
                Employee::query()->where('user_id', $user->id)->where('nip', '!=', $nip)->update(['user_id' => null]);
                $employee->user_id = $user->id;
            }

            $employee->save();
        }

        Employee::query()->where('unit_id', $unit->id)
            ->whereIn('nip', array_map(fn (int $suffix): string => EmployeeSeeder::nip($unit, $suffix), static::INACTIVE_EMPLOYEES))
            ->get()
            ->each(fn (Employee $employee) => $employee->forceFill(['is_active' => false, 'user_id' => null])->save());

        $placeholders = User::query()
            ->whereIn('email', array_map(fn (string $prefix): string => "{$prefix}.{$slug}@".self::DOMAIN, static::INACTIVE_ACCOUNTS))
            ->get();
        Employee::query()->whereIn('user_id', $placeholders->modelKeys())->update(['user_id' => null]);
        User::query()->whereKey($placeholders->modelKeys())->update(['is_active' => false]);

        $this->command?->info("Pegawai {$unit->name} diperbarui. Akun baru memakai kata sandi \"".self::PASSWORD.'".');
    }

    /**
     * @param  array{name: string, role?: RoleName}  $person
     */
    private function personalAccount(array $person, string $nip, string $slug, Unit $unit): User
    {
        $user = User::query()->firstOrNew(['email' => Str::slug($person['name']).".{$slug}@".self::DOMAIN]);
        $user->fill([
            'name' => $person['name'],
            'position' => $person['role']?->label(),
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        if (! $user->exists) {
            $user->employee_id = User::query()->where('employee_id', $nip)->exists() ? null : $nip;
            $user->password = Hash::make(self::PASSWORD);
        }

        $user->save();
        $user->assignRole($person['role'], $unit);

        return $user;
    }
}
