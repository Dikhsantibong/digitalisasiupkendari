<?php

namespace Database\Factories;

use App\Models\ReportPeriod;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportPeriod>
 */
class ReportPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $month = fake()->numberBetween(1, 12);
        $year = fake()->numberBetween(2024, 2026);
        $days = (int) date('t', mktime(0, 0, 0, $month, 1, $year));

        return [
            'unit_id' => Unit::factory(),
            'month' => $month,
            'year' => $year,
            'total_days' => $days,
            'total_hours' => $days * 24,
            'pic_employee_id' => null,
            'locked_at' => null,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes): array => ['locked_at' => now()]);
    }
}
