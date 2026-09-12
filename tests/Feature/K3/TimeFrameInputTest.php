<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3ActivityPlan;
use App\Models\K3ActivityType;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class TimeFrameInputTest extends TestCase
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
            ->get(route('k3.input.time-frame.index'))
            ->assertForbidden();
    }

    public function test_tl_sees_a_row_per_activity_type_with_day_columns(): void
    {
        $unit = Unit::factory()->create();
        K3ActivityType::factory()->count(2)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.input.time-frame.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/input/time-frame')
                ->where('days', 31)
                ->has('rows', 2)
                ->where('filters.plan_type', 'rencana'),
            );
    }

    public function test_saving_stores_the_day_matrix_for_the_plan_type(): void
    {
        $unit = Unit::factory()->create();
        $type = K3ActivityType::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.input.time-frame.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'plan_type' => 'rencana',
            'rows' => [[
                'activity_type_id' => $type->id, 'pic' => 'Budi',
                'days' => ['1' => 'X', '2' => '', '15' => 'X'],
            ]],
        ])->assertRedirect();

        $plan = K3ActivityPlan::query()
            ->where('unit_id', $unit->id)->where('k3_activity_type_id', $type->id)->firstOrFail();

        $this->assertSame(['1' => 'X', '15' => 'X'], $plan->plan_days);
        $this->assertNull($plan->real_days);
        $this->assertSame('Budi', $plan->pic);
    }

    public function test_rencana_and_realisasi_are_stored_on_the_same_row(): void
    {
        $unit = Unit::factory()->create();
        $type = K3ActivityType::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);
        $base = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [['activity_type_id' => $type->id, 'days' => ['1' => 'X']]]];

        $this->actingAs($user)->post(route('k3.input.time-frame.store'), [...$base, 'plan_type' => 'rencana'])->assertRedirect();
        $this->actingAs($user)->post(route('k3.input.time-frame.store'), [...$base, 'plan_type' => 'realisasi'])->assertRedirect();

        $this->assertSame(1, K3ActivityPlan::query()->where('unit_id', $unit->id)->count());
        $plan = K3ActivityPlan::query()->firstOrFail();
        $this->assertSame(['1' => 'X'], $plan->plan_days);
        $this->assertSame(['1' => 'X'], $plan->real_days);
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->post(route('k3.input.time-frame.store'), [
                'unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026, 'plan_type' => 'rencana', 'rows' => [],
            ])
            ->assertForbidden();
    }
}
