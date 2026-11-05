<?php

namespace Database\Factories;

use App\Enums\OpnameJenis;
use App\Models\OperatorOpname;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatorOpname>
 */
class OperatorOpnameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'tanggal' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'jenis' => fake()->randomElement(OpnameJenis::cases()),
            'data' => [],
            'catatan' => null,
        ];
    }
}
