<?php

namespace Tests\Feature\Har;

use App\Enums\MaintenanceScope;
use App\Enums\RoleName;
use App\Enums\SchedulePlanType;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class ScheduleInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_har_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('har.input.schedule.index'))
            ->assertForbidden();
    }

    public function test_tl_sees_a_row_per_machine_with_day_columns(): void
    {
        $unit = Unit::factory()->create();
        Machine::factory()->forUnit($unit)->count(2)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.input.schedule.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/input/schedules')
                ->where('days', 31)
                ->has('rows', 2)
                ->where('filters.scope', 'har')
                ->where('filters.plan_type', 'rencana'),
            );
    }

    public function test_saving_stores_the_day_matrix_per_machine(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.schedule.store'), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'scope' => MaintenanceScope::Har->value,
            'plan_type' => SchedulePlanType::Rencana->value,
            'rows' => [[
                'engine_id' => $engine->id,
                'days' => ['1' => 'P1', '2' => '', '5' => 'P2'],
            ]],
        ])->assertRedirect();

        $schedule = MaintenanceSchedule::query()
            ->where('unit_id', $unit->id)->where('engine_id', $engine->id)
            ->where('scope', 'har')->where('plan_type', 'rencana')->firstOrFail();

        $this->assertSame(['1' => 'P1', '5' => 'P2'], $schedule->schedule_data);
    }

    public function test_scope_and_plan_type_are_stored_separately(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);
        $base = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [['engine_id' => $engine->id, 'days' => ['1' => 'X']]]];

        $this->actingAs($user)->post(route('har.input.schedule.store'), [...$base, 'scope' => 'har', 'plan_type' => 'rencana'])->assertRedirect();
        $this->actingAs($user)->post(route('har.input.schedule.store'), [...$base, 'scope' => 'pelumas', 'plan_type' => 'realisasi'])->assertRedirect();

        $this->assertSame(2, MaintenanceSchedule::query()->where('unit_id', $unit->id)->count());
    }

    public function test_an_engine_from_another_unit_is_rejected(): void
    {
        $unit = Unit::factory()->create();
        $foreignEngine = Machine::factory()->forUnit(Unit::factory()->create())->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->post(route('har.input.schedule.store'), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'scope' => 'har', 'plan_type' => 'rencana',
                'rows' => [['engine_id' => $foreignEngine->id, 'days' => ['1' => 'P1']]],
            ])
            ->assertSessionHasErrors('rows.0.engine_id');
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $ownUnit))
            ->post(route('har.input.schedule.store'), [
                'unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026, 'scope' => 'har', 'plan_type' => 'rencana', 'rows' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('maintenance_schedules', 0);
    }
}
