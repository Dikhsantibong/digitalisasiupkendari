<?php

namespace Tests\Feature\K3;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\WorkModule;
use Database\Seeders\WorkModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class K3FoundationTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_k3_holds_the_k3_permissions_but_not_operasi_or_har(): void
    {
        $user = $this->userWithRole(RoleName::KoordinatorK3, Unit::factory()->create());

        $this->assertTrue($user->hasPermissionTo(PermissionName::K3InputWrite));
        $this->assertTrue($user->hasPermissionTo(PermissionName::K3LaporanView));
        $this->assertTrue($user->hasPermissionTo(PermissionName::K3MonitoringView));
        $this->assertTrue($user->hasPermissionTo(PermissionName::K3MasterManage));

        // The modules are separate: TL K3 gets no operasi/har write access.
        $this->assertFalse($user->hasPermissionTo(PermissionName::OperasiInputWrite));
        $this->assertFalse($user->hasPermissionTo(PermissionName::HarInputWrite));
    }

    public function test_manager_can_only_view_k3_reports_and_monitoring(): void
    {
        $user = $this->userWithRole(RoleName::ManagerUl, ServiceUnit::factory()->create());

        $this->assertTrue($user->hasPermissionTo(PermissionName::K3LaporanView));
        $this->assertTrue($user->hasPermissionTo(PermissionName::K3MonitoringView));

        $this->assertFalse($user->hasPermissionTo(PermissionName::K3InputWrite));
        $this->assertFalse($user->hasPermissionTo(PermissionName::K3MasterManage));
    }

    public function test_the_k3_work_module_is_registered(): void
    {
        $this->seed(WorkModuleSeeder::class);

        $this->assertDatabaseHas('work_modules', ['code' => 'k3', 'is_active' => true]);
        $this->assertNotNull(WorkModule::query()->where('code', 'k3')->first());
    }

    public function test_authorized_user_can_access_k3_jadwal_and_input_hub(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $this->actingAs($user)
            ->get(route('k3.jadwal.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('k3/jadwal/index'));

        $this->actingAs($user)
            ->get(route('k3.input.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('k3/input/index'));

        $this->actingAs($user)
            ->get(route('k3.formulir.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/formulir/index')
                ->has('filters.unit_id')
                ->has('filters.month')
                ->has('filters.year')
                ->has('options.units')
                ->has('options.years')
            );
    }

    public function test_unauthorized_user_cannot_access_k3_jadwal_or_input_hub(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $this->actingAs($user)
            ->get(route('k3.jadwal.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('k3.input.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('k3.formulir.index'))
            ->assertForbidden();
    }
}
