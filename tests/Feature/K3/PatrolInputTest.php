<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\PatrolLocation;
use App\Models\SecurityPatrol;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PatrolInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_k3_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('k3.input.patrol.index'))
            ->assertForbidden();
    }

    public function test_tl_sees_a_row_per_patrol_location_with_day_columns(): void
    {
        $unit = Unit::factory()->create();
        PatrolLocation::factory()->forUnit($unit)->count(2)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.input.patrol.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/input/patrols')
                ->where('days', 31)
                ->has('rows', 2),
            );
    }

    public function test_saving_stores_a_scan_count_per_location_and_day(): void
    {
        $unit = Unit::factory()->create();
        $location = PatrolLocation::factory()->forUnit($unit)->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.input.patrol.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'rows' => [['location_id' => $location->id, 'days' => ['1' => '3', '2' => '0', '5' => '2']]],
        ])->assertRedirect();

        // Positive counts are stored; a zero is not.
        $this->assertSame(2, SecurityPatrol::query()->where('patrol_location_id', $location->id)->count());

        $first = SecurityPatrol::query()->where('patrol_location_id', $location->id)->where('total_scan', 3)->firstOrFail();
        $this->assertSame('2026-08-01', $first->patrol_date->toDateString());

        $fifth = SecurityPatrol::query()->where('patrol_location_id', $location->id)->where('total_scan', 2)->firstOrFail();
        $this->assertSame('2026-08-05', $fifth->patrol_date->toDateString());
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->post(route('k3.input.patrol.store'), [
                'unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026, 'rows' => [],
            ])
            ->assertForbidden();
    }
}
