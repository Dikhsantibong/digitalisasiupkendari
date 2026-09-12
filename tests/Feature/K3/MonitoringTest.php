<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\AccidentReport;
use App\Models\EquipmentCertificate;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\K3\K3MonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_monitoring_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('k3.monitoring.index'))
            ->assertForbidden();
    }

    public function test_manager_ul_can_view_monitoring(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->create(['service_unit_id' => $serviceUnit->id]);

        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))
            ->get(route('k3.monitoring.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('k3/monitoring/index'));
    }

    public function test_certificate_expiry_statuses_are_derived_and_summarised(): void
    {
        $unit = Unit::factory()->create();
        EquipmentCertificate::factory()->forUnit($unit)->expired()->create();
        EquipmentCertificate::factory()->forUnit($unit)->dueSoon()->create();
        EquipmentCertificate::factory()->forUnit($unit)->create(); // aktif (retest a year out)

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.monitoring.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('data.certificates', 3)
                ->where('data.summary.cert_expired', 1)
                ->where('data.summary.cert_mendekati', 1),
            );
    }

    public function test_the_summary_reports_a_nihil_accident_month(): void
    {
        $unit = Unit::factory()->create();
        AccidentReport::factory()->forUnit($unit)->create(['year' => 2026, 'month' => 8, 'is_nihil' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.monitoring.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('data.summary.accident_nihil', true));
    }

    public function test_the_status_helper_flags_the_warning_window(): void
    {
        $service = new K3MonitoringService;

        $this->assertSame('expired', $service->statusFor(Carbon::today()->subDay())['status']);
        $this->assertSame('mendekati', $service->statusFor(Carbon::today()->addDays(30))['status']);
        $this->assertSame('aktif', $service->statusFor(Carbon::today()->addDays(200))['status']);
        $this->assertSame('belum', $service->statusFor(null)['status']);
    }
}
