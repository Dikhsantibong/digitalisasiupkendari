<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkScheduleEntry>
 */
class WorkScheduleEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_schedule_id' => WorkSchedule::factory(),
            'employee_id' => Employee::factory(),
            'work_date' => '2026-08-01',
            'attendance_code_id' => null,
        ];
    }
}
