<?php

namespace Tests\Feature\Admin;

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Enums\UnitStatus;
use App\Enums\UnitTlStructure;
use App\Enums\UnitType;
use App\Models\Employee;
use App\Models\ReportWorkflow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\ReportSignatories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Master Unit (user, 2026-11-18): the TL structure of a unit (Terpisah, TL
 * Ophar, Containerized — chosen by the name), the accounts made with a new
 * unit, and the migration of the existing TL into TL Ophar.
 */
class UnitTlStructureTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    public function test_adding_an_ophar_unit_makes_its_accounts_with_a_tl_ophar_and_no_tl_pemeliharaan(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $serviceUnit = ServiceUnit::factory()->create();

        $response = $this->actingAs($superAdmin)->post(route('admin.units.store'), $this->payload([
            'service_unit_id' => $serviceUnit->id, 'code' => 'pltd-baru', 'name' => 'PLTD Baru', 'create_accounts' => '1',
        ]));

        $unit = Unit::query()->where('code', 'PLTD-BARU')->firstOrFail();
        $response->assertRedirect(route('admin.units.show', $unit))->assertInertiaFlash('provisioned_accounts.password');
        // Without a choice a new unit is TL Ophar.
        $this->assertSame(UnitTlStructure::Ophar, $unit->tl_structure);

        $ophar = User::query()->where('email', 'tl-ophar.pltd-baru@upkendari.co.id')->firstOrFail();
        $this->assertTrue($ophar->hasRole(RoleName::TeamLeaderOphar));
        $this->assertSame([$unit->id], $ophar->roleAssignments()->pluck('unit_id')->all());
        $this->assertSame(EmployeePosition::TeamLeaderOphar->value, $ophar->employee->position);
        $this->assertSame("unit:{$unit->id}:Team Leader Ophar", $ophar->employee->singleton_key);

        foreach (['unit', 'project-leader', 'tl-k3', 'koordinator-operasi', 'koordinator-har', 'koordinator-k3', 'koordinator-logistik', 'koordinator-pdm', 'office-operasi', 'office-har', 'office-k3', 'office-logistik', 'pic-pdm'] as $prefix) {
            $this->assertDatabaseHas('users', ['email' => "{$prefix}.pltd-baru@upkendari.co.id", 'is_active' => true]);
        }
        $this->assertDatabaseMissing('users', ['email' => 'tl-operasi.pltd-baru@upkendari.co.id']);
        $this->assertDatabaseMissing('users', ['email' => 'tl-har.pltd-baru@upkendari.co.id']);

        // Every new account starts with the password "password" for now (shown on the unit page).
        $this->assertSame('password', session('inertia.flash_data')['provisioned_accounts']['password'] ?? null);
        $this->assertTrue(Hash::check('password', $ophar->password));

        // The jabatan resolve right away: the TL Ophar signs for Operasi & Pemeliharaan.
        $this->assertSame($ophar->employee->id, app(ReportSignatories::class)->holder($unit, EmployeePosition::TeamLeaderPemeliharaan)?->id);
    }

    public function test_a_unit_named_containerized_has_no_team_leader_accounts(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($superAdmin)->post(route('admin.units.store'), $this->payload([
            'code' => 'pltd-kendari-cont', 'name' => 'PLTD Kendari Containerized', 'create_accounts' => '1',
        ]))->assertSessionHasNoErrors();

        $unit = Unit::query()->where('code', 'PLTD-KENDARI-CONT')->firstOrFail();
        $this->assertSame(UnitTlStructure::Containerized, $unit->tl_structure);
        $this->assertSame(0, User::query()->where('email', 'like', 'tl-%.pltd-kendari-cont@%')->count());
        $this->assertDatabaseHas('users', ['email' => 'project-leader.pltd-kendari-cont@upkendari.co.id']);
        $this->assertSame(0, Employee::query()->where('unit_id', $unit->id)->where('position', 'like', 'Team Leader%')->count());
    }

    public function test_the_structure_is_chosen_and_changed_and_accounts_are_only_made_when_asked(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        User::factory()->create(['email' => 'unit.pltd-pilih@upkendari.co.id', 'name' => 'Sudah Ada']);

        $this->actingAs($superAdmin)->post(route('admin.units.store'), $this->payload([
            'code' => 'pltd-pilih', 'name' => 'PLTD Pilih', 'tl_structure' => 'terpisah',
        ]))->assertRedirect(route('admin.units.index'));
        $unit = Unit::query()->where('code', 'PLTD-PILIH')->firstOrFail();
        $this->assertSame(UnitTlStructure::Terpisah, $unit->tl_structure);
        $this->assertSame(1, User::query()->where('email', 'like', '%.pltd-pilih@%')->count());

        $this->actingAs($superAdmin)->put(route('admin.units.update', $unit), $this->payload([
            'code' => 'pltd-pilih', 'name' => 'PLTD Pilih', 'tl_structure' => 'ophar',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(UnitTlStructure::Ophar, $unit->refresh()->tl_structure);
        $this->actingAs($superAdmin)->put(route('admin.units.update', $unit), $this->payload([
            'code' => 'pltd-pilih', 'name' => 'PLTD Pilih', 'tl_structure' => 'gabung',
        ]))->assertSessionHasErrors('tl_structure');

        // An e-mail already in use is never touched.
        $other = $this->actingAs($superAdmin)->post(route('admin.units.store'), $this->payload([
            'code' => 'pltd-pilih-2', 'name' => 'PLTD Pilih 2', 'create_accounts' => '1',
        ]));
        $other->assertSessionHasNoErrors();
        $this->assertSame('Sudah Ada', User::query()->where('email', 'unit.pltd-pilih@upkendari.co.id')->value('name'));

        $this->actingAs($superAdmin)->get(route('admin.units.edit', $unit))
            ->assertInertia(fn ($page) => $page->where('unit.tl_structure', 'ophar')->has('options.tlStructures', 3));
    }

    public function test_the_migration_merges_the_team_leaders_into_tl_ophar_and_removes_them_from_containerized_units(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $raha = Unit::factory()->forServiceUnit($serviceUnit)->create(['code' => 'PLTD-RAHA', 'name' => 'PLTD Raha']);
        $kolaka = Unit::factory()->forServiceUnit($serviceUnit)->create(['code' => 'PLTD-KOLAKA', 'name' => 'PLTD Kolaka']);
        $container = Unit::factory()->forServiceUnit($serviceUnit)->create(['code' => 'PLTD-POASIA-CONT', 'name' => 'PLTD Poasia Containerized']);

        [$rahaOperasi, $rahaOperasiUser] = $this->teamLeader($raha, EmployeePosition::TeamLeaderOperasi, RoleName::TeamLeaderOperasi, 'TL Operasi — PLTD Raha');
        [$rahaHar, $rahaHarUser] = $this->teamLeader($raha, EmployeePosition::TeamLeaderPemeliharaan, RoleName::TeamLeaderPemeliharaan, 'TL Pemeliharaan — PLTD Raha');
        [$rahaK3] = $this->teamLeader($raha, EmployeePosition::TeamLeaderK3, RoleName::TeamLeaderK3, 'TL K3 — PLTD Raha');
        [$kolakaHar] = $this->teamLeader($kolaka, EmployeePosition::TeamLeaderPemeliharaan, RoleName::TeamLeaderPemeliharaan, 'TL Har Kolaka');
        [$containerHar, $containerHarUser] = $this->teamLeader($container, EmployeePosition::TeamLeaderPemeliharaan, RoleName::TeamLeaderPemeliharaan, 'TL Har Cont');
        [$containerPl] = $this->teamLeader($container, EmployeePosition::ProjectLeader, RoleName::ProjectLeaderOperasi, 'PL Cont');

        // Open (not signed) steps waiting for a TL who is merged / removed, and one already signed.
        $open = $this->openStep($raha, 'har', $rahaHar, 'Team Leader Pemeliharaan');
        $signed = $this->openStep($raha, 'logistik', $rahaHar, 'Team Leader Pemeliharaan', signed: true);
        $openContainer = $this->openStep($container, 'har', $containerHar, 'Team Leader Pemeliharaan');

        $migration = require database_path('migrations/2026_11_18_155423_restructure_team_leaders_into_tl_ophar.php');
        $migration->up();

        $this->assertSame(UnitTlStructure::Ophar, $raha->fresh()->tl_structure);
        $this->assertSame(UnitTlStructure::Terpisah, $kolaka->fresh()->tl_structure);
        $this->assertSame(UnitTlStructure::Containerized, $container->fresh()->tl_structure);

        // Ophar: TL Operasi → TL Ophar (employee, account, role); TL Pemeliharaan deactivated, not deleted.
        $this->assertSame('Team Leader Ophar', $rahaOperasi->fresh()->position);
        $this->assertSame('TL Ophar — PLTD Raha', $rahaOperasi->fresh()->name);
        $this->assertTrue($rahaOperasiUser->fresh()->hasRole(RoleName::TeamLeaderOphar));
        $this->assertFalse($rahaOperasiUser->fresh()->hasRole(RoleName::TeamLeaderOperasi));
        $this->assertFalse($rahaHar->fresh()->is_active);
        $this->assertFalse($rahaHarUser->fresh()->is_active);
        $this->assertTrue($rahaK3->fresh()->is_active);
        $this->assertTrue($kolakaHar->fresh()->is_active);

        // Containerized: no TL left; the open TL step goes to the Project Leader.
        $this->assertFalse($containerHar->fresh()->is_active);
        $this->assertFalse($containerHarUser->fresh()->is_active);

        $this->assertDatabaseHas('report_workflow_steps', ['id' => $open, 'employee_id' => $rahaOperasi->id, 'position' => 'Team Leader Ophar']);
        $this->assertDatabaseHas('report_workflow_steps', ['id' => $signed, 'employee_id' => $rahaHar->id, 'position' => 'Team Leader Pemeliharaan']);
        $this->assertDatabaseHas('report_workflow_steps', ['id' => $openContainer, 'employee_id' => $containerPl->id, 'position' => 'Project Leader']);

        // The TL Ophar role holds both TL roles' permissions.
        $this->assertTrue($rahaOperasiUser->fresh()->hasPermissionTo('har.pengusahaan.view'));
        $this->assertTrue($rahaOperasiUser->fresh()->hasPermissionTo('operasi.pengusahaan.view'));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $extra): array
    {
        return ['type' => UnitType::Pltd->value, 'status' => UnitStatus::Operating->value, 'is_active' => '1', ...$extra];
    }

    /**
     * @return array{0: Employee, 1: User}
     */
    private function teamLeader(Unit $unit, EmployeePosition $position, RoleName $role, string $name): array
    {
        $user = $this->userWithRole($role, $unit);
        $employee = Employee::factory()->create([
            'unit_id' => $unit->id, 'service_unit_id' => null, 'name' => $name,
            'position' => $position->value, 'user_id' => $user->id, 'is_active' => true,
        ]);

        return [$employee, $user];
    }

    private function openStep(Unit $unit, string $module, Employee $employee, string $position, bool $signed = false): int
    {
        $workflow = ReportWorkflow::query()->create(['module' => $module, 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'status' => 'verifikasi']);

        return $workflow->steps()->create([
            'stage' => 'pengesahan', 'sequence' => 2, 'caption' => 'Menyetujui', 'position' => $position,
            'employee_id' => $employee->id, 'signed_at' => $signed ? now() : null,
        ])->id;
    }
}
