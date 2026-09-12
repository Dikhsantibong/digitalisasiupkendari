<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Enums\WoWaitingReason;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\WorkOrder;
use Database\Seeders\HarMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class WorkOrderInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(HarMasterSeeder::class);
    }

    public function test_a_role_without_har_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('har.input.work-order.index'))
            ->assertForbidden();
    }

    public function test_tl_pemeliharaan_sees_the_work_order_grid(): void
    {
        $unit = Unit::factory()->create();
        Machine::factory()->forUnit($unit)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.input.work-order.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/input/work-orders')
                ->where('filters.unit_id', $unit->id)
                ->has('options.maintenance_types')
                ->has('options.statuses'),
            );
    }

    public function test_saving_resolves_master_codes_and_the_engine(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['name' => 'MAK #1']);
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)
            ->post(route('har.input.work-order.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'rows' => [[
                    'wonum' => 'WO13258',
                    'description' => 'Ganti oli',
                    'type_code' => 'PM',
                    'engine_name' => 'MAK #1',
                    'work_group_code' => 'MECHD',
                    'status_code' => 'CLOSE',
                    'cycle_code' => 'P1',
                    'report_date' => '2026-08-05',
                    'waiting_reason' => 'material',
                    'service_cost' => '1000',
                    'material_cost' => '2000',
                ]],
            ])
            ->assertRedirect();

        $wo = WorkOrder::query()->where('wonum', 'WO13258')->firstOrFail();
        $this->assertSame($unit->id, $wo->unit_id);
        $this->assertSame($engine->id, $wo->engine_id);
        $this->assertSame('PM', $wo->maintenanceType->code);
        $this->assertSame('CLOSE', $wo->status->code);
        $this->assertTrue($wo->status->is_closed);
        $this->assertSame(WoWaitingReason::Material, $wo->waiting_reason);
        $this->assertEquals(1000, (float) $wo->service_cost);
        $this->assertDatabaseHas('report_periods', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]);
    }

    public function test_an_unknown_code_is_stored_as_null(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.work-order.store'), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'rows' => [['wonum' => 'WOX', 'type_code' => 'TIDAK-ADA', 'engine_name' => 'BUKAN MESIN']],
        ])->assertRedirect();

        $wo = WorkOrder::query()->where('wonum', 'WOX')->firstOrFail();
        $this->assertNull($wo->maintenance_type_id);
        $this->assertNull($wo->engine_id);
    }

    public function test_rows_removed_from_the_grid_are_deleted(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);
        $payload = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)->post(route('har.input.work-order.store'), [
            ...$payload,
            'rows' => [['wonum' => 'WO1'], ['wonum' => 'WO2']],
        ])->assertRedirect();
        $this->assertSame(2, WorkOrder::query()->where('unit_id', $unit->id)->count());

        // Re-save with only WO1 → WO2 is removed.
        $this->actingAs($user)->post(route('har.input.work-order.store'), [
            ...$payload,
            'rows' => [['wonum' => 'WO1']],
        ])->assertRedirect();

        $this->assertSame(1, WorkOrder::query()->where('unit_id', $unit->id)->count());
        $this->assertDatabaseMissing('work_orders', ['wonum' => 'WO2']);
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $ownUnit))
            ->post(route('har.input.work-order.store'), [
                'unit_id' => $foreignUnit->id,
                'month' => 8,
                'year' => 2026,
                'rows' => [['wonum' => 'WOZ']],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('work_orders', 0);
    }
}
