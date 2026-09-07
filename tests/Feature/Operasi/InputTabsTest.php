<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Enums\TankFuelType;
use App\Models\AuxiliarySource;
use App\Models\DailyAuxiliaryReading;
use App\Models\DailyFeederReading;
use App\Models\Feeder;
use App\Models\FuelReceipt;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class InputTabsTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_feeder_grid_is_forbidden_without_permission(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operasi.input.feeder.index'))
            ->assertForbidden();
    }

    public function test_saving_feeder_readings_upserts_rows(): void
    {
        $unit = Unit::factory()->create();
        $feeder = Feeder::factory()->forUnit($unit)->create();
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operasi.input.feeder.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'readings' => [
                    ['feeder_id' => $feeder->id, 'day' => 3, 'stand_akhir' => '4521.5'],
                ],
            ])
            ->assertRedirect();

        $reading = DailyFeederReading::query()->where('feeder_id', $feeder->id)->firstOrFail();
        $this->assertSame('2026-08-03', $reading->report_date->toDateString());
        $this->assertSame($unit->id, $reading->unit_id);
        $this->assertEquals(4521.5, (float) $reading->stand_akhir);
    }

    public function test_feeder_readings_cannot_be_saved_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();
        $foreignFeeder = Feeder::factory()->forUnit($foreignUnit)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $ownUnit))
            ->post(route('operasi.input.feeder.store'), [
                'unit_id' => $foreignUnit->id,
                'month' => 8,
                'year' => 2026,
                'readings' => [['feeder_id' => $foreignFeeder->id, 'day' => 1, 'stand_akhir' => '1']],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('daily_feeder_readings', 0);
    }

    public function test_saving_auxiliary_readings_stores_both_meters(): void
    {
        $unit = Unit::factory()->create();
        $source = AuxiliarySource::factory()->forUnit($unit)->create();
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operasi.input.auxiliary.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'readings' => [
                    ['auxiliary_source_id' => $source->id, 'day' => 2, 'stand_kwh_akhir' => '120', 'stand_bbm_akhir' => '55'],
                ],
            ])
            ->assertRedirect();

        $reading = DailyAuxiliaryReading::query()->where('auxiliary_source_id', $source->id)->firstOrFail();
        $this->assertSame('2026-08-02', $reading->report_date->toDateString());
        $this->assertSame($unit->id, $reading->unit_id);
        $this->assertEquals(120, (float) $reading->stand_kwh_akhir);
        $this->assertEquals(55, (float) $reading->stand_bbm_akhir);
    }

    public function test_a_fuel_receipt_can_be_registered_and_deleted(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operasi.input.fuel-receipt.store'), [
                'unit_id' => $unit->id,
                'report_date' => '2026-08-04',
                'fuel_type' => TankFuelType::Hsd->value,
                'supplier' => 'Pertamina',
                'volume_liter' => '30000',
            ])
            ->assertRedirect();

        $receipt = FuelReceipt::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertEquals(30000, (float) $receipt->volume_liter);
        $this->assertSame($user->id, $receipt->input_by);

        $this->actingAs($user)
            ->delete(route('operasi.input.fuel-receipt.destroy', $receipt))
            ->assertRedirect();

        $this->assertDatabaseMissing('fuel_receipts', ['id' => $receipt->id]);
    }

    public function test_a_fuel_receipt_requires_volume(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->post(route('operasi.input.fuel-receipt.store'), [
                'unit_id' => $unit->id,
                'report_date' => '2026-08-04',
                'fuel_type' => TankFuelType::Hsd->value,
            ])
            ->assertSessionHasErrors('volume_liter');
    }
}
