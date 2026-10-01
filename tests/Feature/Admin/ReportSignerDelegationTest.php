<?php

namespace Tests\Feature\Admin;

use App\Enums\EmployeePosition;
use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\ReportSignerDelegation;
use App\Models\ReportWorkflow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\ReportSignatories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Penanda tangan lintas unit: the TL Pemeliharaan of PLTD Poasia also signs the
 * Laporan Pemeliharaan of PLTD Poasia Containerized, as set by the Super Admin.
 */
class ReportSignerDelegationTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PERIOD = ['month' => 9, 'year' => 2026];

    private ServiceUnit $serviceUnit;

    private Unit $poasia;

    private Unit $containerized;

    private User $admin;

    private User $tlPoasia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        Storage::fake('public');
        $this->serviceUnit = ServiceUnit::factory()->create();
        $this->poasia = Unit::factory()->forServiceUnit($this->serviceUnit)->create(['name' => 'PLTD Poasia', 'is_active' => true]);
        $this->containerized = Unit::factory()->forServiceUnit($this->serviceUnit)->create(['name' => 'PLTD Poasia Containerized', 'is_active' => true]);
        $this->admin = $this->userWithRole(RoleName::SuperAdmin);
        $this->tlPoasia = $this->signer(EmployeePosition::TeamLeaderPemeliharaan, RoleName::TeamLeaderPemeliharaan, $this->poasia, 'Budi TL Poasia');
    }

    public function test_without_a_setting_each_unit_keeps_its_own_signer(): void
    {
        $signatories = app(ReportSignatories::class);

        $this->assertSame('Budi TL Poasia', $signatories->holder($this->poasia, EmployeePosition::TeamLeaderPemeliharaan)?->name);
        $this->assertNull($signatories->holder($this->containerized, EmployeePosition::TeamLeaderPemeliharaan));
    }

    public function test_the_super_admin_delegates_a_jabatan_and_access_is_granted(): void
    {
        $this->actingAs($this->admin)->get(route('admin.report-signers.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/report-signers/index')->has('units', 2)->has('delegations', 0));

        $this->delegate()->assertSessionHasNoErrors();

        $delegation = ReportSignerDelegation::query()->firstOrFail();
        $this->assertSame(EmployeePosition::TeamLeaderPemeliharaan, $delegation->position);
        $this->assertSame('Budi TL Poasia', app(ReportSignatories::class)->holder($this->containerized, EmployeePosition::TeamLeaderPemeliharaan)?->name);

        // The signer's account now reaches the delegated unit with its own role.
        $this->assertTrue($this->tlPoasia->fresh()->canAccessUnit($this->containerized));
        $this->assertCount(1, $delegation->granted_assignment_ids);

        $this->actingAs($this->admin)->get(route('admin.report-signers.index'))
            ->assertInertia(fn ($page) => $page->has('delegations', 1)
                ->where('delegations.0.signer', 'Budi TL Poasia')
                ->where('matrix', fn ($matrix): bool => collect($matrix)->firstWhere('unit_id', $this->containerized->id)['cells']['Team Leader Pemeliharaan']['source_unit'] === 'PLTD Poasia'));
    }

    public function test_the_delegated_tl_approves_the_other_units_report(): void
    {
        $this->delegate();
        $koordinator = $this->signer(EmployeePosition::KoordinatorPemeliharaan, RoleName::KoordinatorPemeliharaan, $this->containerized, 'Amir Koordinator');
        $this->signer(EmployeePosition::ManagerUl, RoleName::ManagerUl, $this->serviceUnit, 'Citra Manager');
        $this->signer(EmployeePosition::ProjectLeader, RoleName::ProjectLeaderOperasi, $this->containerized, 'Herwin PL');
        $this->signer(EmployeePosition::OfficePemeliharaan, RoleName::KoordinatorPemeliharaan, $this->containerized, 'Olla Office');
        $maker = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->containerized);
        $target = ['unit_id' => $this->containerized->id, ...self::PERIOD];

        $this->actingAs($maker)->post(route('har.laporan.document.store'), $target + ['format' => 'html', 'content_html' => '<p>Laporan HAR</p>'])->assertRedirect();
        $this->actingAs($maker)->post(route('report-workflow.submit', 'har'), $target)->assertSessionHasNoErrors();

        $workflow = ReportWorkflow::query()->where('unit_id', $this->containerized->id)->with('steps.employee')->firstOrFail();
        $this->assertSame('Budi TL Poasia', $workflow->steps->firstWhere('sequence', 2)->employee->name);

        $this->actingAs($koordinator)->post(route('report-workflow.verify', 'har'), $target)->assertSessionHasNoErrors();

        // The TL of PLTD Poasia opens the Containerized report and approves it.
        $this->actingAs($this->tlPoasia->fresh())->get(route('har.laporan.document.edit', $target))->assertOk()
            ->assertInertia(fn ($page) => $page->where('workflow.can.approve', true));
        $this->actingAs($this->tlPoasia->fresh())->post(route('report-workflow.approve', 'har'), $target)->assertSessionHasNoErrors();

        $this->assertSame(ReportStatus::Disetujui, $workflow->fresh()->status);
    }

    public function test_removing_the_setting_revokes_only_the_access_it_granted(): void
    {
        $this->delegate();
        $delegation = ReportSignerDelegation::query()->firstOrFail();

        $this->actingAs($this->admin)->delete(route('admin.report-signers.destroy', $delegation))->assertRedirect();

        $this->assertDatabaseCount('report_signer_delegations', 0);
        $this->assertFalse($this->tlPoasia->fresh()->canAccessUnit($this->containerized));
        $this->assertTrue($this->tlPoasia->fresh()->canAccessUnit($this->poasia), 'The signer keeps the access of their own unit.');
        $this->assertNull(app(ReportSignatories::class)->holder($this->containerized, EmployeePosition::TeamLeaderPemeliharaan));
    }

    public function test_access_given_by_hand_is_kept_when_the_setting_is_removed(): void
    {
        $this->tlPoasia->assignRole(RoleName::TeamLeaderPemeliharaan, $this->containerized);
        $this->delegate();
        $delegation = ReportSignerDelegation::query()->firstOrFail();
        $this->assertSame([], $delegation->granted_assignment_ids ?? []);

        $this->actingAs($this->admin)->delete(route('admin.report-signers.destroy', $delegation));

        $this->assertTrue($this->tlPoasia->fresh()->canAccessUnit($this->containerized));
    }

    public function test_the_setting_can_be_saved_without_granting_access(): void
    {
        $this->delegate(grantAccess: false)->assertSessionHasNoErrors();

        $this->assertFalse($this->tlPoasia->fresh()->canAccessUnit($this->containerized));
        $this->assertSame('Budi TL Poasia', app(ReportSignatories::class)->holder($this->containerized, EmployeePosition::TeamLeaderPemeliharaan)?->name);
    }

    public function test_validation_and_authorisation(): void
    {
        $this->actingAs($this->admin)->post(route('admin.report-signers.store'), [
            'unit_id' => $this->poasia->id, 'position' => 'Bukan Jabatan', 'source_unit_id' => $this->poasia->id,
        ])->assertSessionHasErrors(['position', 'source_unit_id']);

        $this->actingAs($this->tlPoasia)->get(route('admin.report-signers.index'))->assertForbidden();
        $this->actingAs($this->tlPoasia)->post(route('admin.report-signers.store'), [
            'unit_id' => $this->containerized->id, 'position' => EmployeePosition::TeamLeaderPemeliharaan->value, 'source_unit_id' => $this->poasia->id,
        ])->assertForbidden();
    }

    private function delegate(bool $grantAccess = true): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('admin.report-signers.store'), [
            'unit_id' => $this->containerized->id,
            'position' => EmployeePosition::TeamLeaderPemeliharaan->value,
            'source_unit_id' => $this->poasia->id,
            'grant_access' => $grantAccess,
        ]);
    }

    private function signer(EmployeePosition $position, RoleName $role, ServiceUnit|Unit $scope, string $name): User
    {
        $user = $this->userWithRole($role, $scope);
        Employee::factory()->create([
            'unit_id' => $scope instanceof Unit ? $scope->id : null,
            'service_unit_id' => $scope instanceof ServiceUnit ? $scope->id : null,
            'name' => $name,
            'position' => $position->value,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user->fresh();
    }
}
