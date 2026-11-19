<?php

namespace App\Services\Master;

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The accounts of a new unit, made when a Super Admin adds the unit in
 * Master Data (user, 2026-11-18): the Akun Unit, the Project Leader, the Team
 * Leaders its TL structure has (Terpisah: TL Operasi, TL Pemeliharaan, TL K3;
 * Ophar: TL Ophar, TL K3; Containerized: none — led by the Project Leader),
 * and the Koordinator / Office / PIC of every divisi. Each signer account gets
 * its employee (jabatan) in Master Pegawai, so the approval chain works right
 * away.
 *
 * E-mails follow the existing pattern ({prefix}.{kode-unit}@upkendari.co.id);
 * an e-mail already in use is left untouched. Every new account starts with
 * the password "password" for now (user, 2026-11-19), like the other unit
 * accounts — to be changed after the first sign-in.
 */
class UnitAccountProvisioner
{
    public const DOMAIN = 'upkendari.co.id';

    /** Initial password of every new account (user, 2026-11-19: "password" for now). */
    public const INITIAL_PASSWORD = 'password';

    /**
     * Koordinator & Office of every divisi: e-mail prefix => [jabatan, role].
     *
     * @var array<string, array{0: EmployeePosition, 1: RoleName}>
     */
    private const DIVISI_ACCOUNTS = [
        'koordinator-operasi' => [EmployeePosition::KoordinatorOperasi, RoleName::KoordinatorOperasi],
        'koordinator-har' => [EmployeePosition::KoordinatorPemeliharaan, RoleName::KoordinatorPemeliharaan],
        'koordinator-k3' => [EmployeePosition::KoordinatorK3, RoleName::KoordinatorK3],
        'koordinator-logistik' => [EmployeePosition::KoordinatorLogistik, RoleName::KoordinatorLogistik],
        'koordinator-pdm' => [EmployeePosition::KoordinatorPdm, RoleName::KoordinatorPdm],
        'office-operasi' => [EmployeePosition::OfficeOperasi, RoleName::KoordinatorOperasi],
        'office-har' => [EmployeePosition::OfficePemeliharaan, RoleName::KoordinatorPemeliharaan],
        'office-k3' => [EmployeePosition::OfficeK3, RoleName::KoordinatorK3],
        'office-logistik' => [EmployeePosition::OfficeLogistik, RoleName::KoordinatorLogistik],
        'pic-pdm' => [EmployeePosition::PicPdm, RoleName::KoordinatorPdm],
    ];

    /**
     * Team Leader e-mail prefix and role per jabatan.
     *
     * @var array<string, array{0: string, 1: RoleName, 2: string}>
     */
    private const TEAM_LEADERS = [
        'Team Leader Operasi' => ['tl-operasi', RoleName::TeamLeaderOperasi, 'TL Operasi'],
        'Team Leader Pemeliharaan' => ['tl-har', RoleName::TeamLeaderPemeliharaan, 'TL Pemeliharaan'],
        'Team Leader Ophar' => ['tl-ophar', RoleName::TeamLeaderOphar, 'TL Ophar'],
        'Team Leader K3 & Keamanan' => ['tl-k3', RoleName::TeamLeaderK3, 'TL K3 & Keamanan'],
    ];

    /**
     * Make the unit's accounts.
     *
     * @return array{password: string, accounts: list<array{email: string, name: string, role: string}>}
     */
    public function provision(Unit $unit, ?User $actor = null): array
    {
        $password = self::INITIAL_PASSWORD;
        $slug = Str::slug($unit->code ?: $unit->name) ?: 'unit-'.$unit->id;
        $created = [];

        DB::transaction(function () use ($unit, $actor, $password, $slug, &$created): void {
            $make = function (string $prefix, string $name, RoleName $role, ?EmployeePosition $position) use ($unit, $actor, $password, $slug, &$created): void {
                $account = $this->account("{$prefix}.{$slug}@".self::DOMAIN, $name, $role, $position, $unit, $actor, $password);
                if ($account !== null) {
                    $created[] = ['email' => $account->email, 'name' => $account->name, 'role' => $role->label()];
                }
            };

            $make('unit', "Akun {$unit->name}", RoleName::AkunUnit, null);
            $make('project-leader', "Project Leader {$unit->name}", RoleName::ProjectLeaderOperasi, EmployeePosition::ProjectLeader);

            foreach ($unit->tlStructure()->teamLeaderPositions() as $position) {
                [$prefix, $role, $label] = self::TEAM_LEADERS[$position->value];
                $make($prefix, "{$label} {$unit->name}", $role, $position);
            }

            foreach (self::DIVISI_ACCOUNTS as $prefix => [$position, $role]) {
                $make($prefix, "{$position->value} {$unit->name}", $role, $position);
            }
        });

        return ['password' => $password, 'accounts' => $created];
    }

    /**
     * One account with its role at the unit and, for a jabatan, the employee
     * holding it (an existing unlinked holder is linked instead). Null when
     * the e-mail is already in use.
     */
    private function account(string $email, string $name, RoleName $role, ?EmployeePosition $position, Unit $unit, ?User $actor, string $password): ?User
    {
        if (User::query()->where('email', $email)->exists()) {
            return null;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'position' => $position?->value ?? $role->label(),
            'employee_id' => 'UNIT-'.strtoupper(Str::random(8)),
            'password' => $password,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $user->assignRole($role, $unit, $actor);

        if ($position !== null) {
            $holder = Employee::query()
                ->where('singleton_key', $position->singletonKey($unit->id, $unit->service_unit_id))
                ->first();

            if ($holder === null) {
                Employee::query()->create([
                    'unit_id' => $unit->id,
                    'service_unit_id' => $unit->service_unit_id,
                    'user_id' => $user->id,
                    'name' => $name,
                    'position' => $position->value,
                    'is_active' => true,
                ]);
            } elseif ($holder->user_id === null) {
                $holder->forceFill(['user_id' => $user->id])->save();
            }
        }

        return $user;
    }
}
