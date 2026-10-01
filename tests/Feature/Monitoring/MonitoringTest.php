<?php

namespace Tests\Feature\Monitoring;

use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Models\DailyEngineReport;
use App\Models\Employee;
use App\Models\Machine;
use App\Models\Operasi5s5rJadwal;
use App\Models\Permission;
use App\Models\ReportWorkflow;
use App\Models\Role;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\Monitoring\InputCatalog;
use App\Services\Monitoring\InputCompleteness;
use App\Services\Monitoring\ReportMonitoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private ServiceUnit $serviceUnit;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->serviceUnit = ServiceUnit::factory()->create();
        $this->unit = Unit::factory()->forServiceUnit($this->serviceUnit)->create(['is_active' => true, 'name' => 'PLTD Uji']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_super_admin_opens_every_monitoring_page(): void
    {
        $admin = $this->userWithRole(RoleName::SuperAdmin);
        $query = ['month' => 8, 'year' => 2026];

        $this->actingAs($admin)->get(route('monitoring.index', $query))->assertOk()
            ->assertInertia(fn ($page) => $page->component('monitoring/index')->has('input.groups', count(InputCatalog::GROUPS))->has('laporan.counts'));
        $this->actingAs($admin)->get(route('monitoring.input', $query))->assertOk()
            ->assertInertia(fn ($page) => $page->component('monitoring/input')->has('entries')->has('units', 1)->where('units.0.name', 'PLTD Uji'));
        $this->actingAs($admin)->get(route('monitoring.laporan', $query))->assertOk()
            ->assertInertia(fn ($page) => $page->component('monitoring/laporan')->has('matrix', 1)->has('modules', count(ReportModule::cases())));
    }

    public function test_roles_without_the_permission_are_refused(): void
    {
        foreach ([RoleName::TeamLeaderOperasi, RoleName::KoordinatorPemeliharaan, RoleName::Operator] as $role) {
            $user = $this->userWithRole($role, $this->unit);
            $this->actingAs($user)->get(route('monitoring.index'))->assertForbidden();
            $this->actingAs($user)->get(route('monitoring.input'))->assertForbidden();
            $this->actingAs($user)->get(route('monitoring.laporan'))->assertForbidden();
        }
    }

    public function test_a_role_given_monitoring_view_sees_only_its_own_units(): void
    {
        $role = Role::query()->where('name', RoleName::ManagerUl->value)->firstOrFail();
        $role->permissions()->attach(Permission::query()->where('name', PermissionName::MonitoringView->value)->value('id'));
        $manager = $this->userWithRole(RoleName::ManagerUl, $this->serviceUnit);
        Unit::factory()->create(['is_active' => true, 'name' => 'Unit Lain']);

        $this->actingAs($manager)->get(route('monitoring.input', ['month' => 8, 'year' => 2026]))->assertOk()
            ->assertInertia(fn ($page) => $page->has('units', 1)->where('units.0.name', 'PLTD Uji'));
    }

    public function test_completeness_counts_daily_machine_days_and_monthly_inputs(): void
    {
        $engine = Machine::factory()->create(['unit_id' => $this->unit->id, 'is_active' => true]);
        Machine::factory()->create(['unit_id' => $this->unit->id, 'is_active' => true]);
        foreach (['2026-08-01', '2026-08-02', '2026-08-03'] as $date) {
            DailyEngineReport::factory()->forEngineOnDate($engine, $date)->create();
        }
        Operasi5s5rJadwal::query()->create(['unit_id' => $this->unit->id, 'year' => 2026, 'month' => 8, 'pelaksana' => 'Regu A', 'rencana' => [1], 'realisasi' => [], 'target' => 1]);

        $result = app(InputCompleteness::class)->build(collect([$this->unit]), 8, 2026, Carbon::parse('2026-10-02'));
        $cells = $result['units'][0]['cells'];

        $this->assertSame('past', $result['state']);
        // 3 machine-days of 2 machines × 31 days.
        $this->assertSame(['filled' => 3, 'expected' => 62, 'percent' => 5], array_intersect_key($cells['operasi_pengusahaan:daily_engine_reports'], array_flip(['filled', 'expected', 'percent'])));
        $this->assertSame(100, $cells['operasi:operasi_5s5r_jadwals']['percent']);
        $this->assertSame(0, $cells['operasi:operasi_flm_jadwals']['percent']);
        $this->assertNotNull($cells['operasi:operasi_5s5r_jadwals']['last']);
    }

    public function test_the_current_month_counts_only_the_days_so_far_and_a_future_month_is_not_due(): void
    {
        Machine::factory()->create(['unit_id' => $this->unit->id, 'is_active' => true]);

        $current = app(InputCompleteness::class)->build(collect([$this->unit]), 10, 2026, Carbon::parse('2026-10-02 09:00'));
        $this->assertSame('current', $current['state']);
        $this->assertSame(2, $current['days_due']);
        $this->assertSame(2, $current['units'][0]['cells']['operasi_pengusahaan:daily_engine_reports']['expected']);

        $future = app(InputCompleteness::class)->build(collect([$this->unit]), 12, 2026, Carbon::parse('2026-10-02 09:00'));
        $this->assertSame('future', $future['state']);
        $this->assertNull($future['units'][0]['cells']['operasi:operasi_flm_jadwals']['percent']);
        $this->assertNull($future['units'][0]['percent'] ?? null);
    }

    public function test_report_monitoring_flags_stuck_reports_and_missing_signers(): void
    {
        $koordinator = Employee::factory()->create(['unit_id' => $this->unit->id, 'position' => EmployeePosition::KoordinatorPemeliharaan->value, 'name' => 'Amir Koordinator', 'is_active' => true]);
        $workflow = ReportWorkflow::factory()->create([
            'module' => ReportModule::Har, 'unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026,
            'status' => ReportStatus::Diajukan, 'submitted_at' => Carbon::parse('2026-09-01 08:00'),
        ]);
        $workflow->steps()->create(['stage' => 'pengesahan', 'sequence' => 1, 'caption' => 'Memeriksa', 'position' => EmployeePosition::KoordinatorPemeliharaan->value, 'employee_id' => $koordinator->id]);
        $workflow->logs()->create(['action' => 'ajukan', 'from_status' => 'draft', 'to_status' => 'diajukan', 'user_name' => 'Pembuat', 'created_at' => Carbon::parse('2026-09-01 08:00')]);

        $result = app(ReportMonitoring::class)->build(collect([$this->unit]), 8, 2026, Carbon::parse('2026-09-10 08:00'));

        $cell = $result['matrix'][0]['cells']['har'];
        $this->assertSame('diajukan', $cell['status']);
        $this->assertSame(9, $cell['days']);
        $this->assertTrue($cell['stuck']);
        $this->assertStringContainsString('Amir Koordinator', $cell['waiting_for']);
        $this->assertSame('belum', $result['matrix'][0]['cells']['k3']['status']);
        $this->assertCount(1, $result['stuck']);
        $this->assertSame(1, $result['counts']['diajukan']);

        $missing = $result['missing_signers'][0]['positions'];
        $this->assertContains(EmployeePosition::ManagerUl->value, $missing);
        $this->assertNotContains(EmployeePosition::KoordinatorPemeliharaan->value, $missing);
    }

    public function test_every_catalog_input_links_to_an_existing_page_and_table(): void
    {
        foreach (app(InputCatalog::class)->entries() as $entry) {
            $this->assertTrue(Route::has($entry['route']), "Route {$entry['route']} ({$entry['label']}) is missing.");
            $this->assertTrue(Schema::hasTable($entry['table']), "Table {$entry['table']} is missing.");
        }
    }

    public function test_the_sidebar_permission_is_in_the_catalogue(): void
    {
        $this->assertSame('monitoring.view', PermissionName::MonitoringView->value);
        $this->assertFalse($this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit)->hasPermissionTo(PermissionName::MonitoringView));
        $this->assertTrue($this->userWithRole(RoleName::SuperAdmin)->hasPermissionTo(PermissionName::MonitoringView));
    }
}
