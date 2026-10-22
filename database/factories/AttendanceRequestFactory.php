<?php

namespace Database\Factories;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceRequestType;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRequest>
 */
class AttendanceRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'employee_id' => Employee::factory(),
            'type' => AttendanceRequestType::Izin,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'reason' => fake()->sentence(),
            'status' => AttendanceRequestStatus::MenungguPersetujuan,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttendanceRequestStatus::Disetujui,
            'decided_at' => now(),
        ]);
    }
}
