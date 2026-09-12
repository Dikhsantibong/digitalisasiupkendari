<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\MaintenanceType;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\WoStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class HarMasterCrudTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_har_master_permission_is_forbidden(): void
    {
        // Operator has no har.master.* permission.
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('har.master.index', 'maintenance-types'))
            ->assertForbidden();
    }

    public function test_manager_cannot_manage_har_masters(): void
    {
        // Manager UL can view HAR reports but not the masters.
        $this->actingAs($this->userWithRole(RoleName::ManagerUl, ServiceUnit::factory()->create()))
            ->get(route('har.master.index', 'maintenance-types'))
            ->assertForbidden();
    }

    public function test_tl_pemeliharaan_sees_a_master_with_its_schema(): void
    {
        MaintenanceType::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, Unit::factory()->create()))
            ->get(route('har.master.index', 'maintenance-types'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/master/index')
                ->where('resource.slug', 'maintenance-types')
                ->where('resource.unit_scoped', false)
                ->has('resource.fields')
                ->has('rows', 1),
            );
    }

    public function test_a_global_master_can_be_created_and_deleted(): void
    {
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, Unit::factory()->create());

        $this->actingAs($user)
            ->post(route('har.master.store', 'wo-statuses'), [
                'code' => 'HOLD',
                'name' => 'On Hold',
                'is_closed' => '0',
                'sort_order' => '9',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('wo_statuses', ['code' => 'HOLD', 'name' => 'On Hold']);

        $status = WoStatus::query()->where('code', 'HOLD')->firstOrFail();

        $this->actingAs($user)
            ->delete(route('har.master.destroy', ['resource' => 'wo-statuses', 'id' => $status->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('wo_statuses', ['id' => $status->id]);
    }

    public function test_creating_a_master_validates_required_fields(): void
    {
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, Unit::factory()->create()))
            ->post(route('har.master.store', 'maintenance-types'), ['is_active' => '1'])
            ->assertSessionHasErrors(['code', 'name']);
    }

    public function test_an_unknown_resource_is_not_found(): void
    {
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, Unit::factory()->create()))
            ->get(route('har.master.index', 'tidak-ada'))
            ->assertNotFound();
    }
}
