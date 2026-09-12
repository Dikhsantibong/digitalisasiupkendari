<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\ApdCategory;
use App\Models\K3ActivityType;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class K3MasterCrudTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_k3_master_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('k3.master.index', 'activity-types'))
            ->assertForbidden();
    }

    public function test_manager_cannot_manage_k3_masters(): void
    {
        $this->actingAs($this->userWithRole(RoleName::ManagerUl, ServiceUnit::factory()->create()))
            ->get(route('k3.master.index', 'activity-types'))
            ->assertForbidden();
    }

    public function test_tl_k3_sees_a_global_master_with_its_schema(): void
    {
        K3ActivityType::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, Unit::factory()->create()))
            ->get(route('k3.master.index', 'activity-types'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/master/index')
                ->where('resource.slug', 'activity-types')
                ->where('resource.unit_scoped', false)
                ->has('resource.fields')
                ->has('rows', 1),
            );
    }

    public function test_a_global_master_can_be_created_and_deleted(): void
    {
        $user = $this->userWithRole(RoleName::TeamLeaderK3, Unit::factory()->create());

        $this->actingAs($user)
            ->post(route('k3.master.store', 'activity-types'), [
                'code' => 'INSP-RAMBU',
                'name' => 'Inspeksi Rambu K3',
                'sort_order' => '7',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('k3_activity_types', ['code' => 'INSP-RAMBU', 'name' => 'Inspeksi Rambu K3']);

        $type = K3ActivityType::query()->where('code', 'INSP-RAMBU')->firstOrFail();

        $this->actingAs($user)
            ->delete(route('k3.master.destroy', ['resource' => 'activity-types', 'id' => $type->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('k3_activity_types', ['id' => $type->id]);
    }

    public function test_a_unit_scoped_master_is_created_for_the_selected_unit(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)
            ->post(route('k3.master.store', 'patrol-locations'), [
                'unit_id' => $unit->id,
                'code' => 'POA1',
                'name' => 'Gerbang Utama',
                'sort_order' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('patrol_locations', [
            'unit_id' => $unit->id, 'code' => 'POA1', 'name' => 'Gerbang Utama',
        ]);
    }

    public function test_a_unit_scoped_master_cannot_be_created_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->post(route('k3.master.store', 'patrol-locations'), [
                'unit_id' => $foreignUnit->id,
                'code' => 'POA9',
                'name' => 'Pos Selatan',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('patrol_locations', 0);
    }

    public function test_apd_item_validates_its_global_category_relation(): void
    {
        $unit = Unit::factory()->create();
        $category = ApdCategory::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // A valid global category is accepted.
        $this->actingAs($user)
            ->post(route('k3.master.store', 'apd-items'), [
                'unit_id' => $unit->id,
                'apd_category_id' => $category->id,
                'name' => 'Helm Safety',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('apd_items', ['name' => 'Helm Safety', 'apd_category_id' => $category->id]);

        // A non-existent category is rejected.
        $this->actingAs($user)
            ->post(route('k3.master.store', 'apd-items'), [
                'unit_id' => $unit->id,
                'apd_category_id' => 999999,
                'name' => 'Sarung Tangan',
            ])
            ->assertSessionHasErrors('apd_category_id');
    }

    public function test_creating_a_master_validates_required_fields(): void
    {
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, Unit::factory()->create()))
            ->post(route('k3.master.store', 'activity-types'), ['is_active' => '1'])
            ->assertSessionHasErrors(['code', 'name']);
    }

    public function test_an_unknown_resource_is_not_found(): void
    {
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, Unit::factory()->create()))
            ->get(route('k3.master.index', 'tidak-ada'))
            ->assertNotFound();
    }
}
