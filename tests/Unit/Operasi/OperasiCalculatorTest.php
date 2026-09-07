<?php

namespace Tests\Unit\Operasi;

use App\Enums\CalibrationFactorType;
use App\Enums\FuelType;
use App\Enums\StatusCodeCategory;
use App\Models\CalibrationFactor;
use App\Models\DailyEngineReport;
use App\Models\EngineStatusLog;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\UnitStatusCode;
use App\Services\Operasi\OperasiCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperasiCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function calculator(): OperasiCalculator
    {
        return app(OperasiCalculator::class);
    }

    public function test_opening_stand_of_day_one_carries_over_from_the_previous_month(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);

        // Previous month's last recorded closing stand.
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-07-31')->create([
            'kwh_produksi_stand_akhir' => 100,
        ]);

        $grid = $this->calculator()->buildDailyEngineGrid($engine, 8, 2026);

        $this->assertSame(100.0, $grid['rows'][0]['kwh_produksi_stand_awal']);
    }

    public function test_production_uses_the_calibration_factor_and_stands_chain_within_the_month(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);

        CalibrationFactor::factory()->forUnit($unit)->create([
            'factor_type' => CalibrationFactorType::Kwh,
            'value' => 2,
            'effective_date' => '2026-01-01',
        ]);

        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-07-31')->create([
            'kwh_produksi_stand_akhir' => 100,
            'kwh_pakai_sendiri_stand_akhir' => 0,
            'flowmeter_hsd_stand_akhir' => 0,
        ]);
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-08-01')->create([
            'kwh_produksi_stand_akhir' => 150,
        ]);
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-08-02')->create([
            'kwh_produksi_stand_akhir' => 170,
        ]);

        $grid = $this->calculator()->buildDailyEngineGrid($engine, 8, 2026);

        // Day 1: (150 - 100) * 2 = 100.
        $this->assertSame(100.0, $grid['rows'][0]['kwh_produksi']);
        // Day 2 opening stand chains from day 1 closing (150): (170 - 150) * 2 = 40.
        $this->assertSame(150.0, $grid['rows'][1]['kwh_produksi_stand_awal']);
        $this->assertSame(40.0, $grid['rows'][1]['kwh_produksi']);
    }

    public function test_hours_summary_groups_durations_and_derives_standby(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create();

        $operasi = UnitStatusCode::factory()->create(['unit_id' => null, 'category' => StatusCodeCategory::Operasi]);
        $har = UnitStatusCode::factory()->create(['unit_id' => null, 'category' => StatusCodeCategory::Har]);
        $gangguan = UnitStatusCode::factory()->create(['unit_id' => null, 'category' => StatusCodeCategory::Gangguan]);

        EngineStatusLog::factory()->create([
            'unit_id' => $unit->id, 'engine_id' => $engine->id,
            'report_date' => '2026-08-05', 'status_code_id' => $operasi->id, 'duration_minutes' => 600,
        ]);
        EngineStatusLog::factory()->create([
            'unit_id' => $unit->id, 'engine_id' => $engine->id,
            'report_date' => '2026-08-06', 'status_code_id' => $har->id, 'duration_minutes' => 120,
        ]);
        EngineStatusLog::factory()->create([
            'unit_id' => $unit->id, 'engine_id' => $engine->id,
            'report_date' => '2026-08-07', 'status_code_id' => $gangguan->id, 'duration_minutes' => 60,
        ]);

        $hours = $this->calculator()->hoursSummary($engine, 8, 2026);

        $this->assertSame(10.0, $hours['operasi']);
        $this->assertSame(2.0, $hours['har']);
        $this->assertSame(1.0, $hours['gangguan']);
        $this->assertSame(744.0, $hours['total']);
        // Standby is the balance: 744 - (10 + 2 + 1) = 731.
        $this->assertSame(731.0, $hours['standby']);
    }

    public function test_sfc_divides_fuel_by_production_and_guards_zero(): void
    {
        $sfc = $this->calculator()->sfc(totalBbm: 1000, kwhProduksi: 500, kwhPakaiSendiri: 100);

        $this->assertSame(2.0, $sfc['bruto']);   // 1000 / 500
        $this->assertSame(2.5, $sfc['netto']);   // 1000 / (500 - 100)

        $empty = $this->calculator()->sfc(totalBbm: 1000, kwhProduksi: 0, kwhPakaiSendiri: 0);
        $this->assertNull($empty['bruto']);
        $this->assertNull($empty['netto']);
    }

    public function test_mfo_columns_are_null_for_a_hsd_only_machine(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);

        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-08-01')->create([
            'flowmeter_mfo_stand_akhir' => 500,
        ]);

        $grid = $this->calculator()->buildDailyEngineGrid($engine, 8, 2026);

        $this->assertNull($grid['rows'][0]['pemakaian_mfo']);
        $this->assertNull($grid['rows'][0]['flowmeter_mfo_stand_awal']);
    }

    public function test_period_subtotals_sum_the_matching_days(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);

        // Baseline closing from the previous month so day 1 has an opening stand.
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-07-31')->create([
            'kwh_produksi_stand_akhir' => 0,
            'kwh_pakai_sendiri_stand_akhir' => 0,
        ]);

        // Two days in periode I, both producing 10 kWh net (factor defaults to 1).
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-08-01')->create([
            'kwh_produksi_stand_akhir' => 10,
            'kwh_pakai_sendiri_stand_akhir' => 0,
        ]);
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-08-02')->create([
            'kwh_produksi_stand_akhir' => 20,
            'kwh_pakai_sendiri_stand_akhir' => 0,
        ]);

        $grid = $this->calculator()->buildDailyEngineGrid($engine, 8, 2026);

        // Day1: 10-0=10; Day2: 20-10=10 => periode_1 = 20.
        $this->assertSame(20.0, $grid['summary']['periode_1']['kwh_produksi']);
        $this->assertSame(20.0, $grid['summary']['total']['kwh_produksi']);
    }
}
