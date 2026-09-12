<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class CostInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    private function periodWithCosts(Unit $unit, int $month, int $year, float $service, float $material): ReportPeriod
    {
        $period = ReportPeriod::query()->firstOrCreate(
            ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            ['total_days' => 31, 'total_hours' => 744],
        );
        WorkOrder::factory()->forUnit($unit)->create([
            'report_period_id' => $period->id,
            'service_cost' => $service,
            'material_cost' => $material,
        ]);

        return $period;
    }

    public function test_a_role_without_har_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('har.input.cost.index'))
            ->assertForbidden();
    }

    public function test_costs_default_to_the_sum_of_work_order_costs(): void
    {
        $unit = Unit::factory()->create();
        $this->periodWithCosts($unit, 8, 2026, 100, 200);
        $this->periodWithCosts($unit, 8, 2026, 50, 0); // second WO same period via factory helper

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.input.cost.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('effective.source', 'auto')
                ->where('auto.total', 350)
                ->where('auto.material', 200),
            );
    }

    public function test_a_manual_override_takes_over(): void
    {
        $unit = Unit::factory()->create();
        $this->periodWithCosts($unit, 8, 2026, 100, 200);
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.cost.store'), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'use_manual' => true,
            'service_cost' => '1000',
            'material_cost' => '500',
        ])->assertRedirect();

        $this->assertDatabaseHas('maintenance_costs', ['unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'use_manual' => true]);

        $this->actingAs($user)
            ->get(route('har.input.cost.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('effective.source', 'manual')
                ->where('effective.total', 1500),
            );
    }

    public function test_year_to_date_accumulates_the_effective_totals(): void
    {
        $unit = Unit::factory()->create();
        $this->periodWithCosts($unit, 7, 2026, 100, 0);
        $this->periodWithCosts($unit, 8, 2026, 200, 0);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.input.cost.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->where('ytd', 300));
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $ownUnit))
            ->post(route('har.input.cost.store'), [
                'unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026, 'use_manual' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('maintenance_costs', 0);
    }
}
