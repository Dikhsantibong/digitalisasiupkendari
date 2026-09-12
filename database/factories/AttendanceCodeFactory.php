<?php

namespace Database\Factories;

use App\Enums\AttendanceCodeType;
use App\Models\AttendanceCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceCode>
 */
class AttendanceCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'label' => fake()->word(),
            'type' => AttendanceCodeType::Shift,
            'hitung_hadir' => true,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
