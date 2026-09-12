<?php

namespace Tests\Feature\K3;

use App\Models\AccidentReport;
use App\Models\EmergencyEquipment;
use App\Models\EmergencyFacilityCheck;
use App\Models\K3ActivityPlan;
use App\Models\K3ActivityType;
use App\Models\PatrolLocation;
use App\Models\SecurityPatrol;
use App\Models\Unit;
use App\Services\K3\K3ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_the_month_across_every_input(): void
    {
        $unit = Unit::factory()->create();
        $type = K3ActivityType::factory()->create();

        K3ActivityPlan::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'k3_activity_type_id' => $type->id,
            'pic' => 'Budi', 'plan_days' => ['1' => 'X', '5' => 'X'], 'real_days' => ['1' => 'X'],
        ]);
        AccidentReport::factory()->forUnit($unit)->create(['year' => 2026, 'month' => 8, 'is_nihil' => true]);

        $location = PatrolLocation::factory()->forUnit($unit)->create();
        SecurityPatrol::query()->create(['unit_id' => $unit->id, 'patrol_location_id' => $location->id, 'patrol_date' => '2026-08-03', 'total_scan' => 4]);
        SecurityPatrol::query()->create(['unit_id' => $unit->id, 'patrol_location_id' => $location->id, 'patrol_date' => '2026-08-04', 'total_scan' => 6]);

        $equipment = EmergencyEquipment::factory()->forUnit($unit)->create();
        EmergencyFacilityCheck::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'week' => null,
            'emergency_equipment_id' => $equipment->id, 'jml_total' => 4, 'jml_ready' => 2, 'jml_not_ready' => 2,
        ]);

        $report = app(K3ReportBuilder::class)->monthly($unit, 8, 2026);

        // Time frame: plan=2, real=1 for the activity.
        $this->assertSame(2, $report['time_frame'][0]['plan']);
        $this->assertSame(1, $report['time_frame'][0]['real']);

        // Accidents nihil.
        $this->assertTrue($report['accidents']['nihil']);

        // Patrol cumulative: 4 + 6 = 10 for the location.
        $this->assertSame(10, $report['patrol'][0]['total']);

        // Emergency readiness 2/4 = 50%.
        $this->assertSame('50%', $report['emergency'][0]['percent']);
    }
}
