<?php

namespace Tests\Feature\Har;

use App\Enums\MaintenanceScope;
use App\Enums\RoleName;
use App\Enums\SchedulePlanType;
use App\Models\Machine;
use App\Models\MaintenanceActivity;
use App\Models\MaintenanceActivityMaterial;
use App\Models\MaintenanceActivityTask;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceType;
use App\Models\ReportPeriod;
use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Models\WoStatus;
use Database\Seeders\HarMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(HarMasterSeeder::class);
    }

    private function seedReportData(Unit $unit): void
    {
        $period = ReportPeriod::factory()->forUnit($unit)->create(['month' => 8, 'year' => 2026]);
        $pm = MaintenanceType::query()->where('code', 'PM')->value('id');
        $close = WoStatus::query()->where('code', 'CLOSE')->value('id');
        $appr = WoStatus::query()->where('code', 'APPR')->value('id');

        WorkOrder::factory()->forUnit($unit)->create([
            'report_period_id' => $period->id, 'maintenance_type_id' => $pm, 'wo_status_id' => $close,
            'service_cost' => 100, 'material_cost' => 200, 'waiting_reason' => null,
        ]);
        WorkOrder::factory()->forUnit($unit)->create([
            'report_period_id' => $period->id, 'maintenance_type_id' => $pm, 'wo_status_id' => $appr,
            'service_cost' => null, 'material_cost' => null, 'waiting_reason' => 'material',
        ]);
        ServiceRequest::factory()->forUnit($unit)->create(['report_period_id' => $period->id]);
    }

    public function test_a_role_without_laporan_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('har.laporan.index'))
            ->assertForbidden();
    }

    public function test_tl_pemeliharaan_sees_the_report_index(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.laporan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/laporan/index')
                ->where('can_executive', true),
            );
    }

    public function test_the_monthly_report_summarises_the_data(): void
    {
        $unit = Unit::factory()->create();
        $this->seedReportData($unit);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.laporan.monthly', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/laporan/monthly')
                ->where('data.wo_summary.total', 2)
                ->where('data.wo_summary.complete', 1)
                ->where('data.wo_summary.percent', 50)
                ->where('data.sr_summary.total', 1)
                ->where('data.cost.auto_total', 300)
                ->has('data.wo_by_type', 1)
                ->has('data.wo_waiting', 1),
            );
    }

    public function test_the_executive_summary_condenses_the_report(): void
    {
        $unit = Unit::factory()->create();
        $this->seedReportData($unit);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.laporan.executive', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/laporan/executive')
                ->where('data.wo_total', 2)
                ->where('data.wo_complete', 1)
                ->where('data.waiting_count', 1)
                ->where('data.cost_total', 300),
            );
    }

    public function test_the_monthly_report_includes_activities_schedules_and_attachments(): void
    {
        $unit = Unit::factory()->create();
        $period = ReportPeriod::factory()->forUnit($unit)->create(['month' => 8, 'year' => 2026]);
        $engine = Machine::factory()->forUnit($unit)->create();
        $pm = MaintenanceType::query()->where('code', 'PM')->value('id');

        $activity = MaintenanceActivity::query()->create([
            'unit_id' => $unit->id, 'report_period_id' => $period->id,
            'activity_date' => '2026-08-05', 'engine_id' => $engine->id,
            'maintenance_type_id' => $pm, 'work_result' => 'Baik', 'keterangan' => 'Rutin',
        ]);
        MaintenanceActivityTask::query()->create([
            'activity_id' => $activity->id, 'task_description' => 'Ganti filter', 'sort_order' => 1,
        ]);
        MaintenanceActivityMaterial::query()->create([
            'activity_id' => $activity->id, 'material_name' => 'Filter Oli', 'quantity' => 2, 'unit_of_measure' => 'pcs',
        ]);

        MaintenanceSchedule::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'engine_id' => $engine->id,
            'plan_type' => SchedulePlanType::Rencana->value, 'scope' => MaintenanceScope::Har->value,
            'schedule_data' => ['5' => 'P1'],
        ]);

        MaintenanceAttachment::query()->create([
            'unit_id' => $unit->id, 'report_period_id' => $period->id,
            'title' => 'Foto pekerjaan', 'photo_path' => 'har-attachments/foto.jpg', 'caption' => 'Sesudah',
        ]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.laporan.monthly', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/laporan/monthly')
                ->has('data.activities', 1)
                ->where('data.activities.0.tasks.0', 'Ganti filter')
                ->where('data.activities.0.materials.0.name', 'Filter Oli')
                ->has('data.schedules', 1)
                ->where('data.schedules.0.rows.0.rencana.5', 'P1')
                ->has('data.attachments', 1)
                ->where('data.attachments.0.title', 'Foto pekerjaan'),
            );
    }

    public function test_a_report_cannot_be_built_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $ownUnit))
            ->get(route('har.laporan.monthly', ['unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026]))
            ->assertForbidden();
    }
}
