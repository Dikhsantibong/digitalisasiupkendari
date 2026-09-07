<?php

namespace Tests\Unit\Support;

use App\Support\Indonesian;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndonesianTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function numbers(): array
    {
        return [
            'nol' => [0, 'nol'],
            'sebelas' => [11, 'sebelas'],
            'belasan' => [17, 'tujuh belas'],
            'puluhan' => [21, 'dua puluh satu'],
            'seratus' => [100, 'seratus'],
            'ratusan' => [225, 'dua ratus dua puluh lima'],
            'seribu' => [1000, 'seribu'],
            'tahun' => [2026, 'dua ribu dua puluh enam'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_terbilang_spells_numbers(int $number, string $expected): void
    {
        $this->assertSame($expected, Indonesian::terbilang($number));
    }

    public function test_long_date_is_formatted_in_indonesian(): void
    {
        $this->assertSame('7 September 2026', Indonesian::longDate(Carbon::create(2026, 9, 7)));
    }
}
