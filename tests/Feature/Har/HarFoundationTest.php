<?php

namespace Tests\Feature\Har;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\ReportPeriod;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Services\Har\ManualWorkOrderSource;
use App\Services\Har\WorkOrderSource;
use App\Services\Har\WpcWorkOrderSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class HarFoundationTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_pemeliharaan_holds_the_har_permissions_but_not_operasi(): void
    {
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, Unit::factory()->create());

        $this->assertTrue($user->hasPermissionTo(PermissionName::HarInputWrite));
        $this->assertTrue($user->hasPermissionTo(PermissionName::HarLaporanView));
        $this->assertTrue($user->hasPermissionTo(PermissionName::HarExecutiveView));
        $this->assertTrue($user->hasPermissionTo(PermissionName::HarMasterManage));

        // The two modules are separate: TL Pemeliharaan gets no operasi access.
        $this->assertFalse($user->hasPermissionTo(PermissionName::OperasiInputWrite));
    }

    public function test_manager_can_only_view_har_reports(): void
    {
        $user = $this->userWithRole(RoleName::ManagerUl, ServiceUnit::factory()->create());

        $this->assertTrue($user->hasPermissionTo(PermissionName::HarLaporanView));
        $this->assertTrue($user->hasPermissionTo(PermissionName::HarExecutiveView));

        $this->assertFalse($user->hasPermissionTo(PermissionName::HarInputWrite));
        $this->assertFalse($user->hasPermissionTo(PermissionName::HarMasterManage));
    }

    public function test_the_work_order_source_defaults_to_the_manual_implementation(): void
    {
        $this->assertInstanceOf(ManualWorkOrderSource::class, app(WorkOrderSource::class));
    }

    public function test_the_manual_source_returns_work_orders_for_the_period_only(): void
    {
        $unit = Unit::factory()->create();
        $period = ReportPeriod::factory()->forUnit($unit)->create(['month' => 8, 'year' => 2026]);
        $otherPeriod = ReportPeriod::factory()->forUnit($unit)->create(['month' => 7, 'year' => 2026]);

        WorkOrder::factory()->forUnit($unit)->count(2)->create(['report_period_id' => $period->id]);
        WorkOrder::factory()->forUnit($unit)->create(['report_period_id' => $otherPeriod->id]);

        $source = app(WorkOrderSource::class);

        $this->assertCount(2, $source->workOrders($unit, 8, 2026));
        $this->assertCount(1, $source->workOrders($unit, 7, 2026));
        // A period with no rows yields an empty collection, not an error.
        $this->assertCount(0, $source->workOrders($unit, 9, 2026));
    }

    public function test_the_wpc_source_is_a_placeholder_that_is_not_active(): void
    {
        $this->expectException(RuntimeException::class);

        (new WpcWorkOrderSource)->workOrders(Unit::factory()->create(), 8, 2026);
    }
}
