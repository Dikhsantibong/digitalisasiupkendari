<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeePresence;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeePresence>
 */
class EmployeePresenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'employee_id' => Employee::factory(),
            'work_date' => now()->toDateString(),
            'check_in_at' => now(),
            'check_in_latitude' => -3.9778,
            'check_in_longitude' => 122.5150,
            'check_in_distance_m' => 25,
            'check_in_accuracy_m' => 10,
        ];
    }
}
