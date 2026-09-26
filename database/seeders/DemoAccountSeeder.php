<?php

namespace Database\Seeders;

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo login accounts, one per role, scoped to the organisation:
 *  - Manager UL     : one per service unit
 *  - TL Operasi     : one per unit
 *  - TL Pemeliharaan: one per unit
 *  - TL K3          : one per unit
 *  - Site Leader    : one per unit
 *  - Project Leader : one per unit (senior operator: scheduling + reports)
 *  - Operator       : four per unit (shift A–D)
 *  - Harmes/Harlist : Harmes 1–3 and Harlist 2 per unit (divisi
 *                     Pemeliharaan), each linked to its employee record
 *  - Report signers : the Koordinator of every divisi (pemeriksa of its
 *                     report), Office (Pemeliharaan, Operasi, K3, Logistik)
 *                     and PIC PDM, one per unit, with the TL role of their
 *                     divisi
 *
 * Each account that holds a report-signer jabatan is linked to that
 * employee (employees.user_id, seeded by {@see EmployeeSeeder}), so it can
 * sign the Laporan Pembangkit of its unit.
 *
 * Idempotent: re-running updates the same accounts (keyed by e-mail) and
 * re-applies the role assignment. Every account uses the password "password".
 * The Super Admin account is seeded separately by {@see SuperAdminSeeder}.
 */
class DemoAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PASSWORD = 'password';

    private const DOMAIN = 'upkendari.co.id';

    /**
     * Demo accounts of the report-signer jabatan without one yet: e-mail
     * prefix => [jabatan, role of the divisi].
     *
     * @var array<string, array{0: EmployeePosition, 1: RoleName}>
     */
    private const SIGNER_ACCOUNTS = [
        'koordinator-har' => [EmployeePosition::KoordinatorPemeliharaan, RoleName::TeamLeaderPemeliharaan],
        'koordinator-operasi' => [EmployeePosition::KoordinatorOperasi, RoleName::TeamLeaderOperasi],
        'koordinator-k3' => [EmployeePosition::KoordinatorK3, RoleName::TeamLeaderK3],
        'koordinator-logistik' => [EmployeePosition::KoordinatorLogistik, RoleName::TeamLeaderLogistik],
        'koordinator-pdm' => [EmployeePosition::KoordinatorPdm, RoleName::TeamLeaderPdm],
        'office-har' => [EmployeePosition::OfficePemeliharaan, RoleName::TeamLeaderPemeliharaan],
        'office-operasi' => [EmployeePosition::OfficeOperasi, RoleName::TeamLeaderOperasi],
        'office-k3' => [EmployeePosition::OfficeK3, RoleName::TeamLeaderK3],
        'office-logistik' => [EmployeePosition::OfficeLogistik, RoleName::TeamLeaderLogistik],
        'pic-pdm' => [EmployeePosition::PicPdm, RoleName::TeamLeaderPdm],
    ];

    /**
     * Field maintenance accounts per unit: e-mail prefix => [role, roster
     * suffix of the employee it is linked to ({@see EmployeeSeeder::UNIT_ROSTER})].
     *
     * @var array<string, array{0: RoleName, 1: int}>
     */
    private const MAINTENANCE_ACCOUNTS = [
        'harmes1' => [RoleName::Harmes, 25],
        'harmes2' => [RoleName::Harmes, 26],
        'harmes3' => [RoleName::Harmes, 27],
        'harlist1' => [RoleName::Harlist, 28],
        'harlist2' => [RoleName::Harlist, 29],
    ];

    public function run(): void
    {
        foreach (ServiceUnit::query()->orderBy('id')->get() as $serviceUnit) {
            $slug = $this->slug($serviceUnit->code ?? $serviceUnit->name ?? (string) $serviceUnit->id);
            $this->account(
                "manager.{$slug}@".self::DOMAIN,
                'Manager '.($serviceUnit->name ?? $slug),
                'Manajer Unit Layanan',
                RoleName::ManagerUl,
                $serviceUnit,
                EmployeePosition::ManagerUl,
            );
        }

        foreach (Unit::query()->orderBy('id')->get() as $unit) {
            $slug = $this->slug($unit->code ?? $unit->name ?? (string) $unit->id);
            $name = $unit->name ?? $slug;

            $this->account("tl-operasi.{$slug}@".self::DOMAIN, "TL Operasi {$name}", 'Team Leader Operasi', RoleName::TeamLeaderOperasi, $unit, EmployeePosition::TeamLeaderOperasi);
            $this->account("tl-har.{$slug}@".self::DOMAIN, "TL Pemeliharaan {$name}", 'Team Leader Pemeliharaan', RoleName::TeamLeaderPemeliharaan, $unit, EmployeePosition::TeamLeaderPemeliharaan);
            $this->account("tl-k3.{$slug}@".self::DOMAIN, "TL K3 & Keamanan {$name}", 'Team Leader K3 & Keamanan', RoleName::TeamLeaderK3, $unit, EmployeePosition::TeamLeaderK3);
            $this->account("site-leader.{$slug}@".self::DOMAIN, "Site Leader {$name}", 'Site Leader', RoleName::SiteLeader, $unit);
            $this->account("project-leader.{$slug}@".self::DOMAIN, "Project Leader {$name}", 'Project Leader Operasi', RoleName::ProjectLeaderOperasi, $unit, EmployeePosition::ProjectLeader);

            foreach (self::SIGNER_ACCOUNTS as $prefix => [$position, $role]) {
                $this->account("{$prefix}.{$slug}@".self::DOMAIN, "{$position->value} {$name}", $position->value, $role, $unit, $position);
            }

            for ($n = 1; $n <= 4; $n++) {
                $this->account("operator{$n}.{$slug}@".self::DOMAIN, "Operator {$n} {$name}", 'Operator (Shift '.chr(64 + $n).')', RoleName::Operator, $unit);
            }

            foreach (self::MAINTENANCE_ACCOUNTS as $prefix => [$role, $suffix]) {
                $label = ucfirst(preg_replace('/(\d+)$/', ' $1', $prefix));
                $user = $this->account("{$prefix}.{$slug}@".self::DOMAIN, "{$label} {$name}", $role->label(), $role, $unit);
                $this->linkEmployeeByNip($user, EmployeeSeeder::nip($unit, $suffix));
            }
        }

        $this->command?->info('Akun demo dibuat/diperbarui. Kata sandi semua akun: "'.self::PASSWORD.'".');
    }

    private function account(string $email, string $name, string $position, RoleName $role, ServiceUnit|Unit $scope, ?EmployeePosition $employeePosition = null): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'position' => $position,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);
        if (! $user->exists) {
            $user->employee_id = 'DEMO-'.strtoupper(Str::random(8));
            $user->password = Hash::make(self::PASSWORD);
        }
        $user->save();

        $user->assignRole($role, $scope);

        if ($employeePosition !== null) {
            $this->linkEmployee($user, $employeePosition, $scope);
        }

        return $user;
    }

    /**
     * Link the account to the roster employee with the given NIP, unless
     * either is already linked.
     */
    private function linkEmployeeByNip(User $user, string $nip): void
    {
        if (Employee::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        Employee::query()->where('nip', $nip)->whereNull('user_id')->update(['user_id' => $user->id]);
    }

    /**
     * Link the account to the active holder of the jabatan in its unit
     * (service unit for the Manager UL), unless either is already linked.
     */
    private function linkEmployee(User $user, EmployeePosition $position, ServiceUnit|Unit $scope): void
    {
        $key = $scope instanceof ServiceUnit
            ? $position->singletonKey(null, $scope->id)
            : $position->singletonKey($scope->id, $scope->service_unit_id);

        if ($key === null || Employee::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        Employee::query()->where('singleton_key', $key)->whereNull('user_id')->update(['user_id' => $user->id]);
    }

    private function slug(string $value): string
    {
        return Str::slug($value) ?: 'unit';
    }
}
