<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Enums\StatusCodeCategory;
use App\Enums\TankFuelType;
use App\Models\Feeder;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class MasterCrudTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_master_permission_is_forbidden(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operasi.master.index', 'feeders'))
            ->assertForbidden();
    }

    public function test_an_unknown_resource_is_not_found(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.master.index', 'tidak-ada'))
            ->assertNotFound();
    }

    public function test_tl_operasi_sees_a_unit_scoped_master_with_its_schema(): void
    {
        $unit = Unit::factory()->create();
        Feeder::factory()->forUnit($unit)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.master.index', ['resource' => 'feeders', 'unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/master/index')
                ->where('resource.slug', 'feeders')
                ->where('resource.unit_scoped', true)
                ->has('resource.fields')
                ->has('rows', 1),
            );
    }

    public function test_a_unit_scoped_master_can_be_created(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->post(route('operasi.master.store', 'feeders'), [
                'unit_id' => $unit->id,
                'name' => 'Feeder Baru',
                'is_active' => '1',
                'sort_order' => '5',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('feeders', [
            'unit_id' => $unit->id,
            'name' => 'Feeder Baru',
            'sort_order' => 5,
        ]);
    }

    public function test_creating_a_master_validates_required_fields(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->post(route('operasi.master.store', 'feeders'), [
                'unit_id' => $unit->id,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_a_select_field_rejects_an_invalid_value(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->post(route('operasi.master.store', 'fuel-tanks'), [
                'unit_id' => $unit->id,
                'name' => 'Tangki X',
                'fuel_type' => 'solar', // not a TankFuelType
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('fuel_type');
    }

    public function test_a_global_master_is_created_without_a_unit(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->post(route('operasi.master.store', 'status-codes'), [
                'code' => 'DL',
                'label' => 'Derating Line',
                'category' => StatusCodeCategory::Gangguan->value,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('unit_status_codes', [
            'unit_id' => null,
            'code' => 'DL',
            'category' => StatusCodeCategory::Gangguan->value,
        ]);
    }

    public function test_a_master_can_be_updated_and_deleted(): void
    {
        $unit = Unit::factory()->create();
        $feeder = Feeder::factory()->forUnit($unit)->create(['name' => 'Lama']);
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)
            ->put(route('operasi.master.update', ['resource' => 'feeders', 'id' => $feeder->id]), [
                'unit_id' => $unit->id,
                'name' => 'Baru',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('feeders', ['id' => $feeder->id, 'name' => 'Baru']);

        $this->actingAs($user)
            ->delete(route('operasi.master.destroy', ['resource' => 'feeders', 'id' => $feeder->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('feeders', ['id' => $feeder->id]);
    }

    public function test_a_master_cannot_be_created_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $ownUnit))
            ->post(route('operasi.master.store', 'feeders'), [
                'unit_id' => $foreignUnit->id,
                'name' => 'Selundupan',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('feeders', ['name' => 'Selundupan']);
    }
}
