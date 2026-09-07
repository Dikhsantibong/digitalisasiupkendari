<?php

namespace Database\Factories;

use App\Models\DailyEngineReport;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyEngineReport>
 */
class DailyEngineReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'engine_id' => Machine::factory(),
            'report_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'kwh_produksi_stand_akhir' => fake()->randomFloat(4, 1000, 999999),
            'kwh_pakai_sendiri_stand_akhir' => fake()->randomFloat(4, 10, 9999),
            'beban_puncak_pagi_kw' => fake()->randomFloat(2, 100, 5000),
            'beban_puncak_malam_kw' => fake()->randomFloat(2, 100, 5000),
            'pemakaian_pelumas_liter' => fake()->randomFloat(2, 0, 200),
            'flowmeter_hsd_stand_akhir' => fake()->randomFloat(4, 1000, 999999),
            'flowmeter_hsd_tambah_liter' => fake()->randomFloat(4, 0, 5000),
            'flowmeter_mfo_stand_akhir' => null,
            'flowmeter_mfo_tambah_liter' => null,
            'air_pps_stand_akhir' => null,
            'air_softener_stand_akhir' => null,
            'catatan' => null,
            'input_by' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];
    }

    public function forEngineOnDate(Machine $engine, string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'unit_id' => $engine->unit_id,
            'engine_id' => $engine->getKey(),
            'report_date' => $date,
        ]);
    }
}
