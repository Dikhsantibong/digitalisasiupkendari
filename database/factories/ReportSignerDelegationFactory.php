<?php

namespace Database\Factories;

use App\Enums\EmployeePosition;
use App\Models\ReportSignerDelegation;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportSignerDelegation>
 */
class ReportSignerDelegationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'position' => EmployeePosition::TeamLeaderPemeliharaan,
            'source_unit_id' => Unit::factory(),
            'granted_assignment_ids' => null,
            'created_by' => null,
        ];
    }
}
