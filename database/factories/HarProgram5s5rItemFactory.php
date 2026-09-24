<?php

namespace Database\Factories;

use App\Models\HarProgram5s5rItem;
use App\Models\Unit;
use App\Support\HarProgram5s5r;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarProgram5s5rItem>
 */
class HarProgram5s5rItemFactory extends Factory
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
            'year' => 2026,
            'month' => 8,
            'minggu' => 1,
            'program' => 'ringkas',
            'detail' => HarProgram5s5r::PROGRAMS['ringkas']['detail'],
            'pic' => HarProgram5s5r::DEFAULT_PIC,
            'kondisi_awal' => 'Baik',
            'membersihkan' => true,
            'merapikan' => true,
            'membuang_sampah' => false,
            'mengecat' => false,
            'lainnya' => false,
            'progres' => '76-100',
            'kondisi_akhir' => 'Baik',
            'jumlah' => 2,
            'keterangan' => null,
        ];
    }
}
