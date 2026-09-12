<?php

namespace Tests\Feature\Operator;

use App\Enums\LogsheetStatus;
use App\Enums\RoleName;
use App\Models\LogsheetParameter;
use App\Models\Machine;
use App\Models\OperatorLogsheet;
use App\Models\Unit;
use Database\Seeders\LogsheetParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LogsheetTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(LogsheetParameterSeeder::class);
    }

    private function engineForUnit(Unit $unit): Machine
    {
        return Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
    }

    public function test_a_role_without_logsheet_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::SiteLeader, Unit::factory()->create()))
            ->get(route('operator.logsheet.index'))
            ->assertForbidden();
    }

    public function test_the_operator_cannot_reach_tl_operasi_input(): void
    {
        // The operator only fills logsheets — no daily-report menu.
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('operasi.input.daily-report.index'))
            ->assertForbidden();
    }

    public function test_the_operator_sees_the_logsheet_grid(): void
    {
        $unit = Unit::factory()->create();
        $engine = $this->engineForUnit($unit);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operator.logsheet.index', ['unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-08-10']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/logsheet')
                ->where('can_write', true)
                ->where('is_submitted', false)
                ->has('parameters', 22)
                // Hourly slots + five evening half-hours = 29 rows.
                ->has('rows', 29),
            );
    }

    public function test_the_operator_saves_one_time_slot_with_a_shift(): void
    {
        $unit = Unit::factory()->create();
        $engine = $this->engineForUnit($unit);
        $paramId = LogsheetParameter::query()->where('code', 'LOAD')->value('id');
        $operator = $this->userWithRole(RoleName::Operator, $unit);

        $this->actingAs($operator)->post(route('operator.logsheet.store'), [
            'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-08-10',
            'shift' => 'A', 'time_slot' => '01:00', 'values' => ['p_'.$paramId => '250'],
        ])->assertRedirect();

        $logsheet = OperatorLogsheet::query()->where('engine_id', $engine->id)->firstOrFail();
        $this->assertSame(LogsheetStatus::Draft, $logsheet->status);
        $this->assertSame('A', $logsheet->shift);
        $this->assertSame(1, $logsheet->readings()->count());
        $this->assertSame('01:00', substr((string) $logsheet->readings()->first()->time_slot, 0, 5));

        // Saving another slot adds a row; re-saving the first slot overwrites it.
        $this->actingAs($operator)->post(route('operator.logsheet.store'), [
            'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-08-10',
            'time_slot' => '02:00', 'values' => ['p_'.$paramId => '300'],
        ])->assertRedirect();
        $this->actingAs($operator)->post(route('operator.logsheet.store'), [
            'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-08-10',
            'time_slot' => '01:00', 'values' => ['p_'.$paramId => '260'],
        ])->assertRedirect();

        $this->assertSame(2, $logsheet->fresh()->readings()->count());
        $this->assertEqualsWithDelta(260.0, (float) $logsheet->readings()->where('time_slot', '01:00')->value('value'), 0.0001);
    }

    public function test_operator_only_sees_machines_of_their_own_unit(): void
    {
        $poasia = Unit::factory()->create(['name' => 'PLTD Poasia']);
        $ownMachine = Machine::factory()->create(['unit_id' => $poasia->id, 'is_active' => true, 'name' => 'Cummins #1']);

        $otherUnit = Unit::factory()->create(['name' => 'PLTD Bau-Bau']);
        Machine::factory()->create(['unit_id' => $otherUnit->id, 'is_active' => true, 'name' => 'MAK #1']);

        $this->actingAs($this->userWithRole(RoleName::Operator, $poasia))
            ->get(route('operator.logsheet.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Only the operator's own unit and its machines are offered.
                ->has('options.units', 1)
                ->has('options.machines', 1)
                ->where('options.machines.0.id', $ownMachine->id),
            );
    }

    public function test_an_invalid_shift_is_rejected(): void
    {
        $unit = Unit::factory()->create();
        $engine = $this->engineForUnit($unit);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('operator.logsheet.store'), [
                'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-08-10',
                'shift' => 'Pagi', 'time_slot' => '01:00', 'values' => [],
            ])
            ->assertSessionHasErrors('shift');
    }

    public function test_submitting_locks_the_sheet(): void
    {
        $unit = Unit::factory()->create();
        $engine = $this->engineForUnit($unit);
        $operator = $this->userWithRole(RoleName::Operator, $unit);

        $this->actingAs($operator)->post(route('operator.logsheet.submit'), [
            'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-08-10',
        ])->assertRedirect();

        $logsheet = OperatorLogsheet::query()->where('engine_id', $engine->id)->firstOrFail();
        $this->assertSame(LogsheetStatus::Submitted, $logsheet->status);
        $this->assertNotNull($logsheet->submitted_at);

        // Reopening shows it locked, and a further save is rejected.
        $this->actingAs($operator)
            ->get(route('operator.logsheet.index', ['unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-08-10']))
            ->assertInertia(fn ($page) => $page->where('can_write', false)->where('is_submitted', true));

        $this->actingAs($operator)->post(route('operator.logsheet.store'), [
            'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-08-10', 'time_slot' => '01:00', 'values' => [],
        ])->assertStatus(422);
    }

    public function test_tl_operasi_can_view_but_not_write(): void
    {
        $unit = Unit::factory()->create();
        $engine = $this->engineForUnit($unit);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operator.logsheet.index', ['unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-08-10']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->post(route('operator.logsheet.store'), [
                'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-08-10', 'time_slot' => '01:00', 'values' => [],
            ])
            ->assertForbidden();
    }

    public function test_an_operator_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();
        $foreignEngine = $this->engineForUnit($foreignUnit);

        $this->actingAs($this->userWithRole(RoleName::Operator, $ownUnit))
            ->post(route('operator.logsheet.store'), [
                'unit_id' => $foreignUnit->id, 'engine_id' => $foreignEngine->id, 'log_date' => '2026-08-10', 'time_slot' => '01:00', 'values' => [],
            ])
            ->assertForbidden();
    }
}
