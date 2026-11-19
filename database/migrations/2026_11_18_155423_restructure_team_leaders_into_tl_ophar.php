<?php

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Enums\UnitTlStructure;
use App\Models\Employee;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TL Ophar (user, 2026-11-18): TL Operasi and TL Pemeliharaan become one
     * TL Ophar in every unit except PLTD Poasia, Wua-Wua, Bau-Bau and Kolaka;
     * a containerized unit has no TL (its Project Leader takes the TL steps).
     *
     * - units.tl_structure (terpisah / ophar / containerized);
     * - role tl_ophar = the permissions the TL Operasi and TL Pemeliharaan
     *   roles hold now (Role & Akses changes kept);
     * - Ophar unit: the TL Operasi employee & account become TL Ophar; the TL
     *   Pemeliharaan employee & account are deactivated (not deleted);
     * - containerized unit: its TL employees & accounts are deactivated;
     * - approval steps not signed yet move to the new holder, so no report
     *   waits for a deactivated TL.
     *
     * down() restores the column, role and jabatan, not the deactivated
     * accounts (re-activate those in Master Pegawai / Pengguna).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('units', 'tl_structure')) {
            Schema::table('units', function (Blueprint $table): void {
                $table->string('tl_structure', 20)->default(UnitTlStructure::Terpisah->value)->after('operator_pengusahaan');
            });
        }

        foreach (DB::table('units')->get(['id', 'code', 'name']) as $unit) {
            DB::table('units')->where('id', $unit->id)->update(['tl_structure' => UnitTlStructure::initialFor($unit->code, $unit->name)->value]);
        }

        $ophar = $this->createOpharRole();
        $tlOperasi = Role::query()->where('name', RoleName::TeamLeaderOperasi->value)->first();

        foreach (DB::table('units')->get(['id', 'service_unit_id', 'tl_structure']) as $unit) {
            match (UnitTlStructure::from($unit->tl_structure)) {
                UnitTlStructure::Ophar => $this->mergeIntoOphar($unit->id, $ophar, $tlOperasi),
                UnitTlStructure::Containerized => $this->removeTeamLeaders($unit->id),
                UnitTlStructure::Terpisah => null,
            };
        }
    }

    public function down(): void
    {
        $ophar = Role::query()->where('name', RoleName::TeamLeaderOphar->value)->first();
        $tlOperasi = Role::query()->where('name', RoleName::TeamLeaderOperasi->value)->first();

        Employee::query()->where('position', EmployeePosition::TeamLeaderOphar->value)->get()
            ->each(function (Employee $employee): void {
                $employee->position = EmployeePosition::TeamLeaderOperasi->value;
                $employee->save();
            });

        if ($ophar !== null) {
            if ($tlOperasi !== null) {
                RoleAssignment::query()->where('role_id', $ophar->id)->update(['role_id' => $tlOperasi->id]);
            }
            $ophar->permissions()->detach();
            $ophar->delete();
        }

        if (Schema::hasColumn('units', 'tl_structure')) {
            Schema::table('units', function (Blueprint $table): void {
                $table->dropColumn('tl_structure');
            });
        }
    }

    private function createOpharRole(): Role
    {
        $role = Role::query()->updateOrCreate(
            ['name' => RoleName::TeamLeaderOphar->value],
            [
                'display_name' => RoleName::TeamLeaderOphar->label(),
                'scope' => RoleName::TeamLeaderOphar->scope(),
                'description' => RoleName::TeamLeaderOphar->description(),
                'is_system' => true,
            ],
        );

        // What the TL Operasi and TL Pemeliharaan roles hold now (as adjusted in Role & Akses).
        $permissionIds = DB::table('permission_role')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->whereIn('roles.name', [RoleName::TeamLeaderOperasi->value, RoleName::TeamLeaderPemeliharaan->value])
            ->distinct()
            ->pluck('permission_role.permission_id')
            ->all();
        $role->permissions()->syncWithoutDetaching($permissionIds);

        return $role;
    }

    /**
     * The TL Operasi becomes the TL Ophar (or the TL Pemeliharaan, when the
     * unit has no TL Operasi); the other TL of the pair is deactivated.
     */
    private function mergeIntoOphar(int $unitId, Role $ophar, ?Role $tlOperasi): void
    {
        $operasi = $this->activeHolder($unitId, EmployeePosition::TeamLeaderOperasi);
        $pemeliharaan = $this->activeHolder($unitId, EmployeePosition::TeamLeaderPemeliharaan);
        $keep = $operasi ?? $pemeliharaan;

        if ($keep === null) {
            return;
        }

        $removed = $keep->is($operasi) ? $pemeliharaan : null;
        if ($removed !== null) {
            $this->deactivate($removed);
        }

        $from = $keep->position;
        $keep->position = EmployeePosition::TeamLeaderOphar->value;
        $keep->name = $this->renamed($keep->name);
        $keep->save();

        $user = $keep->user_id !== null ? User::query()->find($keep->user_id) : null;
        if ($user !== null) {
            $user->forceFill(['position' => EmployeePosition::TeamLeaderOphar->value, 'name' => $this->renamed($user->name)])->save();
            $fromRole = $from === EmployeePosition::TeamLeaderOperasi->value ? $tlOperasi : Role::query()->where('name', RoleName::TeamLeaderPemeliharaan->value)->first();
            if ($fromRole !== null) {
                RoleAssignment::query()->where('user_id', $user->id)->where('role_id', $fromRole->id)->where('unit_id', $unitId)->update(['role_id' => $ophar->id]);
            }
            if (! RoleAssignment::query()->where('user_id', $user->id)->where('role_id', $ophar->id)->where('unit_id', $unitId)->exists()) {
                $user->assignRole($ophar, Unit::query()->find($unitId));
            }
            $user->forgetAccessCache();
        }

        // Open approval steps of both TL go to the TL Ophar.
        $this->moveOpenSteps(array_filter([$keep->id, $removed?->id]), $keep->id, EmployeePosition::TeamLeaderOphar->value);
    }

    /**
     * A containerized unit has no Team Leader: its TL are deactivated and
     * their open approval steps go to the unit's Project Leader.
     */
    private function removeTeamLeaders(int $unitId): void
    {
        $teamLeaders = Employee::query()
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->whereIn('position', [
                EmployeePosition::TeamLeaderOperasi->value,
                EmployeePosition::TeamLeaderPemeliharaan->value,
                EmployeePosition::TeamLeaderK3->value,
                EmployeePosition::TeamLeaderOphar->value,
            ])
            ->get();

        $teamLeaders->each(fn (Employee $employee) => $this->deactivate($employee));

        $projectLeader = $this->activeHolder($unitId, EmployeePosition::ProjectLeader);
        if ($projectLeader !== null) {
            $this->moveOpenSteps($teamLeaders->pluck('id')->all(), $projectLeader->id, EmployeePosition::ProjectLeader->value);
        }
    }

    private function activeHolder(int $unitId, EmployeePosition $position): ?Employee
    {
        return Employee::query()->where('unit_id', $unitId)->where('position', $position->value)->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * Deactivate the employee and the account linked to it (never deleted).
     */
    private function deactivate(Employee $employee): void
    {
        $employee->is_active = false;
        $employee->save();

        if ($employee->user_id !== null) {
            $user = User::query()->find($employee->user_id);
            if ($user !== null) {
                $user->forceFill(['is_active' => false])->save();
                $user->forgetAccessCache();
            }
        }
    }

    /**
     * @param  list<int>  $fromEmployeeIds
     */
    private function moveOpenSteps(array $fromEmployeeIds, int $toEmployeeId, string $position): void
    {
        if ($fromEmployeeIds === []) {
            return;
        }

        foreach (['report_workflow_steps', 'ul_report_steps'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->whereIn('employee_id', $fromEmployeeIds)
                ->whereNull('signed_at')
                ->update(['employee_id' => $toEmployeeId, 'position' => $position, 'updated_at' => now()]);
        }
    }

    /**
     * "TL Operasi PLTD Raha" → "TL Ophar PLTD Raha" (a person's name is kept).
     */
    private function renamed(string $name): string
    {
        return (string) preg_replace('/^(TL|Team Leader) (Operasi|Pemeliharaan)\b/u', '$1 Ophar', $name);
    }
};
