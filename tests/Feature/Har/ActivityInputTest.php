<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\MaintenanceActivity;
use App\Models\MaintenanceType;
use App\Models\Unit;
use Database\Seeders\HarMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class ActivityInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(HarMasterSeeder::class);
    }

    private function basePayload(Unit $unit): array
    {
        return ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];
    }

    public function test_a_role_without_har_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('har.input.activity.index'))
            ->assertForbidden();
    }

    public function test_saving_creates_the_activity_with_tasks_and_materials(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create();
        $type = MaintenanceType::query()->firstOrFail();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.activity.store'), [
            ...$this->basePayload($unit),
            'activity_date' => '2026-08-12',
            'engine_id' => $engine->id,
            'maintenance_type_id' => $type->id,
            'work_result' => 'Baik',
            'no_lh05' => 'LH-05/1',
            'tasks' => [['task_description' => 'Bersihkan filter'], ['task_description' => 'Ganti oli']],
            'materials' => [['material_name' => 'Oli', 'part_number' => 'X1', 'quantity' => '2', 'unit_of_measure' => 'L']],
        ])->assertRedirect();

        $activity = MaintenanceActivity::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame($engine->id, $activity->engine_id);
        $this->assertSame('Baik', $activity->work_result);
        $this->assertCount(2, $activity->tasks);
        $this->assertCount(1, $activity->materials);
        $this->assertEquals(2, (float) $activity->materials->first()->quantity);
        $this->assertDatabaseHas('report_periods', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]);
    }

    public function test_updating_replaces_the_task_and_material_lines(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.activity.store'), [
            ...$this->basePayload($unit),
            'activity_date' => '2026-08-12',
            'tasks' => [['task_description' => 'A'], ['task_description' => 'B']],
            'materials' => [['material_name' => 'M1']],
        ])->assertRedirect();

        $activity = MaintenanceActivity::query()->where('unit_id', $unit->id)->firstOrFail();

        $this->actingAs($user)->put(route('har.input.activity.update', $activity), [
            ...$this->basePayload($unit),
            'activity_date' => '2026-08-13',
            'tasks' => [['task_description' => 'C']],
            'materials' => [],
        ])->assertRedirect();

        $activity->refresh();
        $this->assertCount(1, $activity->tasks);
        $this->assertSame('C', $activity->tasks->first()->task_description);
        $this->assertCount(0, $activity->materials);
        $this->assertSame(1, MaintenanceActivity::query()->where('unit_id', $unit->id)->count());
    }

    public function test_activity_date_is_required(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->post(route('har.input.activity.store'), [...$this->basePayload($unit), 'tasks' => []])
            ->assertSessionHasErrors('activity_date');
    }

    public function test_an_engine_from_another_unit_is_rejected(): void
    {
        $unit = Unit::factory()->create();
        $foreignEngine = Machine::factory()->forUnit(Unit::factory()->create())->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->post(route('har.input.activity.store'), [
                ...$this->basePayload($unit),
                'activity_date' => '2026-08-12',
                'engine_id' => $foreignEngine->id,
            ])
            ->assertSessionHasErrors('engine_id');
    }

    public function test_an_activity_can_be_deleted_with_its_lines(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.activity.store'), [
            ...$this->basePayload($unit),
            'activity_date' => '2026-08-12',
            'tasks' => [['task_description' => 'A']],
            'materials' => [['material_name' => 'M1']],
        ])->assertRedirect();

        $activity = MaintenanceActivity::query()->where('unit_id', $unit->id)->firstOrFail();

        $this->actingAs($user)->delete(route('har.input.activity.destroy', $activity))->assertRedirect();

        $this->assertDatabaseMissing('maintenance_activities', ['id' => $activity->id]);
        $this->assertDatabaseCount('maintenance_activity_tasks', 0);
        $this->assertDatabaseCount('maintenance_activity_materials', 0);
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $ownUnit))
            ->post(route('har.input.activity.store'), [
                ...$this->basePayload($foreignUnit),
                'activity_date' => '2026-08-12',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('maintenance_activities', 0);
    }
}
