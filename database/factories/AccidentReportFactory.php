<?php

namespace Database\Factories;

use App\Enums\AccidentCategory;
use App\Models\AccidentReport;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccidentReport>
 */
class AccidentReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'category' => AccidentCategory::Pak,
            'luka_ringan' => 0,
            'luka_berat' => 0,
            'meninggal' => 0,
            'is_nihil' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
