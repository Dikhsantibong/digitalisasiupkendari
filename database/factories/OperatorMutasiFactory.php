<?php

namespace Database\Factories;

use App\Models\OperatorMutasi;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatorMutasi>
 */
class OperatorMutasiFactory extends Factory
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
            'tanggal' => '2026-09-09',
            'shift' => 'sore',
            'mesin' => [
                ['machine_id' => null, 'nama' => 'CUMMINS #6', 'level_bbm' => '✓', 'tambah_bbm' => '✓', 'pelumas' => 'normal', 'status' => 'operasi'],
                ['machine_id' => null, 'nama' => 'CUMMINS #7', 'level_bbm' => '✓', 'tambah_bbm' => null, 'pelumas' => 'normal', 'status' => 'standby'],
            ],
            'tangki' => [['nama' => '1', 'level_cm' => '120']],
            'peralatan' => [['nama' => 'Radio HT', 'ada' => true, 'jumlah' => 1], ['nama' => 'Senter', 'ada' => true, 'jumlah' => 3]],
            'kejadian' => [['jam' => '16:00', 'uraian' => 'Terima tugas dari shift B ke shift D dengan kondisi CM 6,8 operasi normal']],
            'gangguan_mesin' => 'CM 7 kebocoran pada sisi lube hose',
            'catatan' => null,
            'regu_penyerah' => 'D',
            'penyerah_nama' => fake()->name(),
        ];
    }

    /** Handed over by the regu (signed) and accepted by the next one. */
    public function received(): static
    {
        return $this->state(fn (): array => [
            'diserahkan_at' => now(),
            'regu_penerima' => 'A',
            'penerima_nama' => fake()->name(),
            'diterima_at' => now(),
        ]);
    }
}
