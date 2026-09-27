<?php

namespace Database\Factories;

use App\Models\HarLaporanKegiatan;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarLaporanKegiatan>
 */
class HarLaporanKegiatanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'category' => HarLaporanKegiatan::MESIN,
            'year' => 2026,
            'month' => 8,
            'sort_order' => 0,
            'activity_date' => '2026-08-04',
            'groups' => [[
                'mesin' => 'MAK#3',
                'judul' => 'PREVENTIV MAINTENANCE P2',
                'jenis_har' => 'PREVENTIV',
                'uraian' => ['Pengecekan tekanan kompresi semua cyl.head', 'Pengecekan timing fuel pump'],
            ]],
            'hasil_pekerjaan' => 'Baik',
            'material_nama' => 'Kaos tangan, majun, rinso',
            'jumlah' => '1 psg, 0,2 kg, 3 bh',
            'no_wo_spki' => fake()->numerify('WO#####'),
        ];
    }

    public function listrik(): static
    {
        return $this->state(fn (): array => ['category' => HarLaporanKegiatan::LISTRIK]);
    }
}
