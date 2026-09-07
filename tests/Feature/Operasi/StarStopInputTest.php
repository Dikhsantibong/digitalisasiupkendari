<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Enums\StatusCodeCategory;
use App\Models\EngineStatusLog;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\UnitStatusCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class StarStopInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_operasi_permission_is_forbidden(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operasi.input.star-stop.index'))
            ->assertForbidden();
    }

    public function test_tl_operasi_sees_the_star_stop_log_and_hours(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create();
        UnitStatusCode::factory()->create(['unit_id' => null, 'category' => StatusCodeCategory::Operasi]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.input.star-stop.index', [
                'unit_id' => $unit->id,
                'engine_id' => $engine->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/input/star-stop')
                ->where('engine.id', $engine->id)
                ->has('options.status_codes', 1)
                ->has('hours'),
            );
    }

    public function test_adding_an_entry_computes_its_duration(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create();
        $status = UnitStatusCode::factory()->create(['unit_id' => null, 'category' => StatusCodeCategory::Operasi]);
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operasi.input.star-stop.store'), [
                'unit_id' => $unit->id,
                'engine_id' => $engine->id,
                'status_code_id' => $status->id,
                'report_date' => '2026-08-10',
                'start_datetime' => '2026-08-10 06:00',
                'stop_datetime' => '2026-08-10 14:30',
                'operator_name' => 'Budi',
            ])
            ->assertRedirect();

        $log = EngineStatusLog::query()->where('engine_id', $engine->id)->firstOrFail();

        $this->assertSame(510, $log->duration_minutes);
        $this->assertSame('Budi', $log->operator_name);
        $this->assertSame($user->id, $log->input_by);
    }

    public function test_stop_before_start_is_rejected(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create();
        $status = UnitStatusCode::factory()->create(['unit_id' => null, 'category' => StatusCodeCategory::Operasi]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->post(route('operasi.input.star-stop.store'), [
                'unit_id' => $unit->id,
                'engine_id' => $engine->id,
                'status_code_id' => $status->id,
                'report_date' => '2026-08-10',
                'start_datetime' => '2026-08-10 14:00',
                'stop_datetime' => '2026-08-10 06:00',
            ])
            ->assertSessionHasErrors('stop_datetime');
    }

    public function test_a_user_cannot_add_an_entry_for_a_unit_outside_their_scope(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();
        $foreignEngine = Machine::factory()->forUnit($foreignUnit)->create();
        $status = UnitStatusCode::factory()->create(['unit_id' => null, 'category' => StatusCodeCategory::Operasi]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $ownUnit))
            ->post(route('operasi.input.star-stop.store'), [
                'unit_id' => $foreignUnit->id,
                'engine_id' => $foreignEngine->id,
                'status_code_id' => $status->id,
                'report_date' => '2026-08-10',
                'start_datetime' => '2026-08-10 06:00',
                'stop_datetime' => '2026-08-10 08:00',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('engine_status_logs', 0);
    }

    public function test_a_user_can_delete_an_entry_in_their_scope(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create();
        $status = UnitStatusCode::factory()->create(['unit_id' => null, 'category' => StatusCodeCategory::Operasi]);
        $log = EngineStatusLog::factory()->create([
            'unit_id' => $unit->id,
            'engine_id' => $engine->id,
            'status_code_id' => $status->id,
        ]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->delete(route('operasi.input.star-stop.destroy', $log))
            ->assertRedirect();

        $this->assertDatabaseMissing('engine_status_logs', ['id' => $log->id]);
    }
}
