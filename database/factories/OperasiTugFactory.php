<?php

namespace Database\Factories;

use App\Enums\TugJenis;
use App\Models\Machine;
use App\Models\OperasiTug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperasiTug>
 */
class OperasiTugFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'machine_id' => Machine::factory(),
            'unit_id' => fn (array $attributes): int => Machine::query()->findOrFail($attributes['machine_id'])->unit_id,
            'jenis' => TugJenis::Pelumas,
            'month' => fake()->numberBetween(1, 12),
            'year' => 2026,
            'nomor' => fake()->numerify('000##').'/LOG.00.02/550231/2026',
            'pekerjaan' => 'RUTIN',
            'no_spk' => null,
            'cost_center' => fake()->numerify('25031331##'),
            'kode_perkiraan' => '1023',
            'tanggal_dokumen' => null,
        ];
    }

    /**
     * A TUG of the given machine and period.
     */
    public function forMachine(Machine $machine, int $month, int $year, TugJenis $jenis = TugJenis::Pelumas): static
    {
        return $this->state(fn (array $attributes): array => [
            'jenis' => $jenis,
            'machine_id' => $machine->id,
            'unit_id' => $machine->unit_id,
            'month' => $month,
            'year' => $year,
        ]);
    }
}
