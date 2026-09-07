<?php

namespace Tests\Feature\Operasi;

use App\Enums\FuelType;
use App\Enums\RoleName;
use App\Models\DailyEngineReport;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class DailyReportInputTest extends TestCase
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
            ->get(route('operasi.input.daily-report.index'))
            ->assertForbidden();
    }

    public function test_tl_operasi_sees_the_input_grid_for_their_unit(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.input.daily-report.index', [
                'unit_id' => $unit->id,
                'engine_id' => $engine->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/input/daily-report')
                ->where('engine.id', $engine->id)
                ->has('grid.rows', 31)
                ->where('can_write', true),
            );
    }

    public function test_saving_input_persists_rows_and_creates_the_period(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operasi.input.daily-report.store'), [
                'unit_id' => $unit->id,
                'engine_id' => $engine->id,
                'month' => 8,
                'year' => 2026,
                'rows' => [
                    ['day' => 1, 'kwh_produksi_stand_akhir' => '123.5', 'flowmeter_mfo_stand_akhir' => '999'],
                ],
            ])
            ->assertRedirect();

        $report = DailyEngineReport::query()->where('engine_id', $engine->id)->firstOrFail();

        $this->assertSame('2026-08-01', $report->report_date->toDateString());
        $this->assertEquals(123.5, (float) $report->kwh_produksi_stand_akhir);
        $this->assertSame($user->id, $report->input_by);
        // MFO is stripped for a HSD-only machine.
        $this->assertNull($report->flowmeter_mfo_stand_akhir);

        $this->assertDatabaseHas('report_periods', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'total_days' => 31,
        ]);
    }

    public function test_a_user_cannot_save_input_for_a_unit_outside_their_scope(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();
        $foreignEngine = Machine::factory()->forUnit($foreignUnit)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $ownUnit))
            ->post(route('operasi.input.daily-report.store'), [
                'unit_id' => $foreignUnit->id,
                'engine_id' => $foreignEngine->id,
                'month' => 8,
                'year' => 2026,
                'rows' => [['day' => 1, 'kwh_produksi_stand_akhir' => '10']],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('daily_engine_reports', 0);
    }
}
