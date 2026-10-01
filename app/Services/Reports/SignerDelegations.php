<?php

namespace App\Services\Reports;

use App\Enums\EmployeePosition;
use App\Models\ReportSignerDelegation;
use App\Models\RoleAssignment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates and removes "penanda tangan lintas unit" settings. Optionally the
 * signer's account is given access to the delegated unit with the same
 * unit-scoped roles it holds at the source unit — so they can open the report
 * they verify — and that access is revoked again with the delegation (only
 * the assignments it created; access granted by hand is never touched).
 */
class SignerDelegations
{
    public function __construct(private readonly ReportSignatories $signatories) {}

    /**
     * Every jabatan that signs a Laporan Pembangkit (pengesahan & tanda tangan), in chain order.
     *
     * @return list<EmployeePosition>
     */
    public static function positions(): array
    {
        return [
            EmployeePosition::KoordinatorOperasi,
            EmployeePosition::KoordinatorPemeliharaan,
            EmployeePosition::KoordinatorK3,
            EmployeePosition::KoordinatorPdm,
            EmployeePosition::KoordinatorLogistik,
            EmployeePosition::TeamLeaderOperasi,
            EmployeePosition::TeamLeaderPemeliharaan,
            EmployeePosition::TeamLeaderK3,
            EmployeePosition::ManagerUl,
            EmployeePosition::ProjectLeader,
            EmployeePosition::OfficeOperasi,
            EmployeePosition::OfficePemeliharaan,
            EmployeePosition::OfficeK3,
            EmployeePosition::OfficePdm,
            EmployeePosition::OfficeLogistik,
            EmployeePosition::PicPdm,
        ];
    }

    public function save(Unit $unit, EmployeePosition $position, Unit $source, bool $grantAccess, User $actor): ReportSignerDelegation
    {
        return DB::transaction(function () use ($unit, $position, $source, $grantAccess, $actor): ReportSignerDelegation {
            $delegation = ReportSignerDelegation::query()->firstOrNew(['unit_id' => $unit->id, 'position' => $position->value]);

            // Changing the source: give back the access granted for the previous signer first.
            if ($delegation->exists) {
                $this->revokeAccess($delegation);
            }

            $delegation->fill([
                'source_unit_id' => $source->id,
                'granted_assignment_ids' => null,
                'created_by' => $delegation->created_by ?? $actor->id,
            ])->save();

            if ($grantAccess) {
                $delegation->granted_assignment_ids = $this->grantAccess($unit, $source, $position, $actor);
                $delegation->save();
            }

            $this->signatories->flush();

            return $delegation->fresh(['unit', 'sourceUnit']);
        });
    }

    public function remove(ReportSignerDelegation $delegation): void
    {
        DB::transaction(function () use ($delegation): void {
            $this->revokeAccess($delegation);
            $delegation->delete();
            $this->signatories->flush();
        });
    }

    /**
     * Give the signer account the unit-scoped roles it holds at the source
     * unit, at the delegated unit too. Returns the assignments created.
     *
     * @return list<int>
     */
    private function grantAccess(Unit $unit, Unit $source, EmployeePosition $position, User $actor): array
    {
        $user = $this->signatories->ownHolder($source, $position)?->user;

        if (! $user instanceof User) {
            return [];
        }

        $created = [];
        $roles = $user->roleAssignments()->with('role')->where('unit_id', $source->id)->get()->pluck('role')->unique('id');

        foreach ($roles as $role) {
            if ($user->roleAssignments()->where('role_id', $role->id)->where('unit_id', $unit->id)->exists()) {
                continue;
            }

            $created[] = $user->assignRole($role, $unit, $actor)->id;
        }

        $user->forgetAccessCache();

        return $created;
    }

    private function revokeAccess(ReportSignerDelegation $delegation): void
    {
        $ids = $delegation->granted_assignment_ids ?? [];

        if ($ids === []) {
            return;
        }

        RoleAssignment::query()->whereIn('id', $ids)->with('user')->get()->each(function (RoleAssignment $assignment): void {
            $user = $assignment->user;
            $assignment->delete();
            $user?->forgetAccessCache();
        });
    }
}
