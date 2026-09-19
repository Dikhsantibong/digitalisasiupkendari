<?php

namespace Database\Factories;

use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Models\ReportWorkflow;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportWorkflow>
 */
class ReportWorkflowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module' => ReportModule::Har,
            'unit_id' => Unit::factory(),
            'month' => fake()->numberBetween(1, 12),
            'year' => 2026,
            'status' => ReportStatus::Draft,
        ];
    }
}
