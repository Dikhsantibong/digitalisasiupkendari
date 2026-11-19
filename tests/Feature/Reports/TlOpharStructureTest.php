<?php

namespace Tests\Feature\Reports;

use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Enums\UnitTlStructure;
use App\Models\Employee;
use App\Models\K3DocumentRecord;
use App\Models\OperasiReportDocument;
use App\Models\ReportWorkflow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\ReportSignatories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * TL Ophar (user, 2026-11-18): in every unit except PLTD Poasia, Wua-Wua,
 * Bau-Bau and Kolaka the TL Operasi and TL Pemeliharaan are one TL Ophar, who
 * approves both divisi; a containerized unit has no TL and its Project Leader
 * takes the TL steps. The four separate units keep their two TL.
 */
class TlOpharStructureTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PERIOD = ['month' => 8, 'year' => 2026];

    private ServiceUnit $serviceUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->serviceUnit = ServiceUnit::factory()->create();
        $this->signer(EmployeePosition::ManagerUl, RoleName::ManagerUl, $this->serviceUnit, 'Citra Manager');
    }

    public function test_the_tl_ophar_approves_both_the_operasi_and_the_pemeliharaan_reports(): void
    {
        $unit = $this->unit('PLTD Raha', UnitTlStructure::Ophar);
        $ophar = $this->signer(EmployeePosition::TeamLeaderOphar, RoleName::TeamLeaderOphar, $unit, 'Rudi TL Ophar');
        $koordinatorHar = $this->signer(EmployeePosition::KoordinatorPemeliharaan, RoleName::KoordinatorPemeliharaan, $unit, 'Amir Koordinator Har');
        $koordinatorOperasi = $this->signer(EmployeePosition::KoordinatorOperasi, RoleName::KoordinatorOperasi, $unit, 'Ahmad Koordinator Operasi');
        $this->signer(EmployeePosition::ProjectLeader, RoleName::ProjectLeaderOperasi, $unit, 'Herwin PL');
        $this->signer(EmployeePosition::OfficePemeliharaan, RoleName::KoordinatorPemeliharaan, $unit, 'Olla Office Har');
        $this->signer(EmployeePosition::OfficeOperasi, RoleName::KoordinatorOperasi, $unit, 'Oki Office Operasi');
        $target = ['unit_id' => $unit->id, ...self::PERIOD];

        // Pemeliharaan: the TL step is the TL Ophar (named so in the chain).
        $harMaker = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $this->actingAs($harMaker)->post(route('har.laporan.document.store'), $target + [
            'format' => 'html',
            'content_html' => '<p>Laporan HAR</p><table id="ttd-pengesahan"><tr><td></td></tr></table><table id="ttd-laporan"><tr><td></td></tr></table>',
        ])->assertRedirect();
        $this->actingAs($harMaker)->post(route('report-workflow.submit', 'har'), $target)->assertSessionHasNoErrors();

        $har = $this->workflow('har', $unit);
        $step = $har->steps->firstWhere('sequence', 2);
        $this->assertSame('Team Leader Ophar', $step->position);
        $this->assertSame('Rudi TL Ophar', $step->employee->name);

        $this->actingAs($koordinatorHar)->post(route('report-workflow.verify', 'har'), $target)->assertSessionHasNoErrors();
        $this->actingAs($ophar)->post(route('report-workflow.approve', 'har'), $target)->assertSessionHasNoErrors();
        $this->assertSame(ReportStatus::Disetujui, $this->workflow('har', $unit)->status);

        // Operasi: the same TL Ophar approves.
        OperasiReportDocument::query()->create($target + ['format' => 'html', 'content_html' => '<p>Laporan</p>', 'report_code' => 'laporan-operasi-bulanan', 'content_version' => 7]);
        $operasiMaker = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);
        $this->actingAs($operasiMaker)->post(route('report-workflow.submit', 'operasi'), $target)->assertSessionHasNoErrors();
        $this->assertSame('Team Leader Ophar', $this->workflow('operasi', $unit)->steps->firstWhere('sequence', 2)->position);
        $this->actingAs($koordinatorOperasi)->post(route('report-workflow.verify', 'operasi'), $target)->assertSessionHasNoErrors();
        $this->actingAs($ophar)->post(route('report-workflow.approve', 'operasi'), $target)->assertSessionHasNoErrors();
        $this->assertSame(ReportStatus::Disetujui, $this->workflow('operasi', $unit)->status);
    }

    public function test_a_containerized_unit_has_its_project_leader_take_the_team_leader_step(): void
    {
        $unit = $this->unit('PLTD Poasia Containerized', UnitTlStructure::Containerized);
        $projectLeader = $this->signer(EmployeePosition::ProjectLeader, RoleName::ProjectLeaderOperasi, $unit, 'Herwin PL');
        $koordinator = $this->signer(EmployeePosition::KoordinatorK3, RoleName::KoordinatorK3, $unit, 'Kiki Koordinator K3');
        $this->signer(EmployeePosition::OfficeK3, RoleName::KoordinatorK3, $unit, 'Opi Office K3');
        // A TL K3 left in the unit is not asked: a containerized unit has no TL.
        $this->signer(EmployeePosition::TeamLeaderK3, RoleName::TeamLeaderK3, $unit, 'Sari TL K3');
        $target = ['unit_id' => $unit->id, ...self::PERIOD];

        K3DocumentRecord::query()->create($target + ['format' => 'html', 'content_html' => '<p>Laporan</p>', 'type' => 'bulanan', 'content_version' => 14]);
        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))->post(route('report-workflow.submit', 'k3'), $target)->assertSessionHasNoErrors();

        $step = $this->workflow('k3', $unit)->steps->firstWhere('sequence', 2);
        $this->assertSame('Project Leader', $step->position);
        $this->assertSame('Herwin PL', $step->employee->name);

        $this->actingAs($koordinator)->post(route('report-workflow.verify', 'k3'), $target)->assertSessionHasNoErrors();
        $this->actingAs($projectLeader)->post(route('report-workflow.approve', 'k3'), $target)->assertSessionHasNoErrors();
        $this->assertSame(ReportStatus::Disetujui, $this->workflow('k3', $unit)->status);
    }

    public function test_the_separate_units_keep_their_tl_operasi_and_tl_pemeliharaan(): void
    {
        $unit = $this->unit('PLTD Kolaka', UnitTlStructure::Terpisah);
        $this->signer(EmployeePosition::TeamLeaderOperasi, RoleName::TeamLeaderOperasi, $unit, 'Rudi TL Operasi');
        $this->signer(EmployeePosition::TeamLeaderPemeliharaan, RoleName::TeamLeaderPemeliharaan, $unit, 'Budi TL Har');
        $signatories = app(ReportSignatories::class);

        $this->assertSame('Rudi TL Operasi', $signatories->holder($unit, EmployeePosition::TeamLeaderOperasi)?->name);
        $this->assertSame('Budi TL Har', $signatories->holder($unit, EmployeePosition::TeamLeaderPemeliharaan)?->name);
        $this->assertSame('Team Leader Pemeliharaan', $signatories->positionLabel($unit, EmployeePosition::TeamLeaderPemeliharaan));
        $this->assertSame(EmployeePosition::TeamLeaderPemeliharaan, ReportModule::Logistik->teamLeaderPosition());

        // A unit loaded without the column still resolves its structure.
        $ophar = $this->unit('PLTD Raha', UnitTlStructure::Ophar);
        $this->signer(EmployeePosition::TeamLeaderOphar, RoleName::TeamLeaderOphar, $ophar, 'Rudi TL Ophar');
        $partial = Unit::query()->select(['id', 'name', 'service_unit_id'])->find($ophar->id);
        $this->assertSame('Rudi TL Ophar', app(ReportSignatories::class)->holder($partial, EmployeePosition::TeamLeaderPemeliharaan)?->name);
    }

    public function test_the_tl_ophar_role_opens_both_the_operasi_and_the_pemeliharaan_menus(): void
    {
        $unit = $this->unit('PLTD Raha', UnitTlStructure::Ophar);
        $ophar = $this->userWithRole(RoleName::TeamLeaderOphar, $unit);

        foreach ([PermissionName::OperasiPengusahaanView, PermissionName::OperasiPengusahaanWrite, PermissionName::OperasiLaporanView,
            PermissionName::HarPengusahaanView, PermissionName::HarPengusahaanWrite, PermissionName::HarLaporanView, PermissionName::LogistikLaporanView] as $permission) {
            $this->assertSame(
                $this->userWithRole(RoleName::TeamLeaderOperasi, $unit)->hasPermissionTo($permission) || $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit)->hasPermissionTo($permission),
                $ophar->hasPermissionTo($permission),
                $permission->value,
            );
        }
        $this->assertTrue($ophar->hasPermissionTo(PermissionName::OperasiPengusahaanView));
        $this->assertTrue($ophar->hasPermissionTo(PermissionName::HarPengusahaanView));

        $this->actingAs($ophar)->get(route('har.pengusahaan.index', ['section' => 'input', 'unit_id' => $unit->id]))->assertOk();
        $this->actingAs($ophar)->get(route('operasi.pengusahaan.index', ['section' => 'input', 'unit_id' => $unit->id]))->assertOk();
    }

    private function unit(string $name, UnitTlStructure $structure): Unit
    {
        return Unit::factory()->forServiceUnit($this->serviceUnit)->create(['name' => $name, 'tl_structure' => $structure])->fresh();
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

    private function workflow(string $module, Unit $unit): ReportWorkflow
    {
        return ReportWorkflow::query()->where('module', $module)->where('unit_id', $unit->id)->with('steps.employee')->firstOrFail();
    }
}
