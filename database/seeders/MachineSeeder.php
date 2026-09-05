<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the generating machines (DATA MASTER MESIN PEMBANGKIT) beneath each
 * generating unit.
 *
 * Values mirror the source spreadsheet: a `null` capacity means the source
 * capacity column was empty (N/A); the literal `-` in a type or serial number
 * is preserved as it appears in the source.
 *
 * @var array<string, list<array{name: string, type: string|null, serial: string|null, capacity: float|null}>>
 */
class MachineSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Machines keyed by the code of the unit they belong to.
     *
     * @var array<string, list<array{name: string, type: string|null, serial: string|null, capacity: float|null}>>
     */
    private const MACHINES = [
        'PLTD-WUAWUA' => [
            ['name' => 'MAK #1', 'type' => '8 M 453 AK', 'serial' => '26881', 'capacity' => 1.7],
            ['name' => 'MAK #2', 'type' => '8 M 453 AK', 'serial' => '26882', 'capacity' => 1.6],
            ['name' => 'MAK #3', 'type' => '8 M 453 AK', 'serial' => '26883', 'capacity' => 1.7],
            ['name' => 'MAK #4', 'type' => '8 M 453 C', 'serial' => '27263', 'capacity' => null],
            ['name' => 'MAK #5', 'type' => '8 M 453 C', 'serial' => '27262', 'capacity' => null],
        ],
        'PLTD-POASIA' => [
            ['name' => 'MIRRLEES #1', 'type' => 'ESL 16 MK 2', 'serial' => '01 - 44 - 98', 'capacity' => 1.7],
            ['name' => 'MIRRLEES #2', 'type' => 'ESL 16 MK 2', 'serial' => '01 - 44 - 98', 'capacity' => 1.7],
            ['name' => 'MIRRLEES #4', 'type' => 'ESL 16 MK 2', 'serial' => '01 - 37- 97', 'capacity' => 1.7],
            ['name' => 'MIRRLEES #5', 'type' => 'ESL 16 MK 2', 'serial' => '01 - 41 - 98', 'capacity' => 1.7],
        ],
        'PLTD-POASIA-CONT' => [
            ['name' => 'CUMMINS #1', 'type' => 'KTA 50-G8', 'serial' => '25426138', 'capacity' => 1],
            ['name' => 'CUMMINS #2', 'type' => 'KTA 50-G8', 'serial' => '25426233', 'capacity' => 1],
            ['name' => 'CUMMINS #3', 'type' => 'KTA 50-G8', 'serial' => '25423797', 'capacity' => 1],
        ],
        'PLTD-KOLAKA' => [
            ['name' => 'DAIHATSU #1', 'type' => '6PSHTc-26Dm', 'serial' => '6265143', 'capacity' => 0.4],
            ['name' => 'DAIHATSU #2', 'type' => '6PSHTc-26D', 'serial' => '6263916', 'capacity' => 0.4],
            ['name' => 'DAIHATSU #3', 'type' => '6PSHTc-26D', 'serial' => '6263905', 'capacity' => 0.3],
            ['name' => 'NIGATA #3', 'type' => '6L 25 CXE', 'serial' => '17487', 'capacity' => 0.3],
            ['name' => 'MAK #1', 'type' => '8M 453 AK', 'serial' => '26879', 'capacity' => 1.8],
            ['name' => 'MAK #2', 'type' => '8M 453 AK', 'serial' => '26880', 'capacity' => 1.8],
        ],
        'PLTD-LANIPANIPA' => [
            ['name' => 'DEUTZ BV #2', 'type' => 'BV 8M 628', 'serial' => '7300 369', 'capacity' => 0.85],
            ['name' => 'DEUTZ BV #3', 'type' => 'BV 8M 628', 'serial' => '7208 739', 'capacity' => 0.85],
            ['name' => 'DEUTZ BV #4', 'type' => 'BV 8M 628', 'serial' => '7208 765', 'capacity' => 0.9],
            ['name' => 'MAN #1', 'type' => 'D 2842 LE 201', 'serial' => '-', 'capacity' => 0.7],
        ],
        'PLTD-LADUMPI' => [
            ['name' => 'YANMAR #1', 'type' => '6ML-HTS', 'serial' => '7030 FMF-1', 'capacity' => 0.1],
            ['name' => 'YANMAR #2', 'type' => '6ML-HTS', 'serial' => '7030 FMF-2', 'capacity' => 0.1],
            ['name' => 'CUMMINS #2', 'type' => 'KTA 50 G8', 'serial' => '25425966', 'capacity' => 0.1],
        ],
        'PLTD-BAUBAU' => [
            ['name' => 'DAIHATSU #3', 'type' => '6DL-28', 'serial' => 'DL 62870368', 'capacity' => 0.8],
            ['name' => 'DAIHATSU #4', 'type' => '6DL-28', 'serial' => 'DL 62870369', 'capacity' => 0.8],
            ['name' => 'DEUTZ BV #1', 'type' => 'BV 8M 628', 'serial' => '7300 392', 'capacity' => 0.7],
            ['name' => 'DEUTZ BV #2', 'type' => 'BV 8M 628', 'serial' => '7208 751', 'capacity' => 0.7],
            ['name' => 'DEUTZ BV #3', 'type' => 'BV 8M 628', 'serial' => '7300 396', 'capacity' => 0.8],
        ],
        'PLTD-PASARWAJO' => [
            ['name' => 'CUMMINS #1', 'type' => 'KTA 50 G8', 'serial' => '25423111', 'capacity' => 1],
            ['name' => 'CUMMINS #2', 'type' => 'KTA 50 G8', 'serial' => '25426139', 'capacity' => 1],
        ],
        'PLTM-WINNING' => [
            ['name' => 'BIWATER FRANCIS #1', 'type' => 'TURBIN FRANCIS', 'serial' => 'H564A/260T/545/SG', 'capacity' => 9.78],
            ['name' => 'BIWATER FRANCIS #2', 'type' => 'TURBIN FRANCIS', 'serial' => 'H564B/260T/545/SG', 'capacity' => 9.78],
        ],
        'PLTD-RAHA' => [
            ['name' => 'DAIHATSU #3', 'type' => '6PSHTc-26D', 'serial' => '6264 652', 'capacity' => 0.4],
            ['name' => 'MIRRLEES', 'type' => 'K.8 MAJOR', 'serial' => '73-04-01', 'capacity' => 0.3],
            ['name' => 'DEUTZ BV #1', 'type' => 'BV BM-628', 'serial' => '7208 750', 'capacity' => 0.3],
            ['name' => 'CUMMINS #1', 'type' => 'KTA50-G8', 'serial' => '2542 3268', 'capacity' => 0.7],
            ['name' => 'CUMMINS #2', 'type' => 'KTA50-G8', 'serial' => '2542 3782', 'capacity' => 0.7],
            ['name' => 'CUMMINS #3', 'type' => 'KTA50-G8', 'serial' => '2542 6034', 'capacity' => 0.7],
            ['name' => 'CUMMINS #4', 'type' => 'KTA50-G8', 'serial' => '2542 6984', 'capacity' => 0.7],
            ['name' => 'CUMMINS #5', 'type' => 'KTA50-G8', 'serial' => '2542 5963', 'capacity' => 0.7],
            ['name' => 'MITSUBISHI #1', 'type' => 'S16R-PTA-S', 'serial' => '21993', 'capacity' => 0.6],
            ['name' => 'MITSUBISHI #2', 'type' => 'S16R-PTA-S', 'serial' => '21975', 'capacity' => 0.6],
            ['name' => 'MITSUBISHI #4', 'type' => 'S16R-PTA-S', 'serial' => '21953', 'capacity' => 0.6],
            ['name' => 'MITSUBISHI #3', 'type' => 'S16R-PTA-S', 'serial' => '21994', 'capacity' => null],
        ],
        'PLTD-WANGIWANGI' => [
            ['name' => 'DAIHATSU #1', 'type' => '6PSHTc-26D', 'serial' => '6264 169', 'capacity' => 0.7],
            ['name' => 'DAIHATSU #2', 'type' => '6PSHTc-26Dm', 'serial' => '6265 154', 'capacity' => 0.7],
            ['name' => 'SWD #1', 'type' => 'DRO 218 K', 'serial' => '10853', 'capacity' => 0.8],
            ['name' => 'SWD #2', 'type' => 'DRO 218 K', 'serial' => '10999', 'capacity' => 0.8],
            ['name' => 'SWD #3', 'type' => 'DRO 218 K', 'serial' => '11000', 'capacity' => 0.8],
            ['name' => 'CUMMINS #1', 'type' => 'KTA50-G8', 'serial' => '2542 3109', 'capacity' => 0.9],
            ['name' => 'CUMMINS #2', 'type' => 'KTA50-G8', 'serial' => '25426137', 'capacity' => 0.9],
            ['name' => 'MITSUBISHI #1', 'type' => 'S16R-PTA-S', 'serial' => '21987', 'capacity' => 0.7],
            ['name' => 'MITSUBISHI #2', 'type' => 'S16R-PTA-S', 'serial' => '22006', 'capacity' => 0.7],
            ['name' => 'MITSUBISHI #3', 'type' => 'S16R-PTA-S', 'serial' => '22004', 'capacity' => 0.7],
            ['name' => 'MITSUBISHI #4', 'type' => 'S16R-PTA-S', 'serial' => '22002', 'capacity' => 0.7],
            ['name' => 'MITSHUBISHI #5', 'type' => 'S16R-PTA-S', 'serial' => '220015', 'capacity' => null],
            ['name' => 'MITSHUBISHI #6', 'type' => 'S16R-PTA-S', 'serial' => '220038', 'capacity' => null],
        ],
        'PLTD-LANGARA' => [
            ['name' => 'CUMMINS #1', 'type' => 'QSK23-G3', 'serial' => '8500 2827', 'capacity' => 0.8],
            ['name' => 'CUMMINS #2', 'type' => 'QSK23-G3', 'serial' => '8500 2814', 'capacity' => 0.8],
            ['name' => 'CATERPILLAR', 'type' => '3406', 'serial' => 'CAT 00000KC2G06393', 'capacity' => 1.1],
            ['name' => 'MAN #1', 'type' => 'D2840LE201', 'serial' => '4641 6802 2016 76', 'capacity' => 0.7],
            ['name' => 'MAN #2', 'type' => 'D2840LE201', 'serial' => '4940 1741 0142 01', 'capacity' => 0.7],
            ['name' => 'MITSUBISHI #1', 'type' => 'S16R-PTA-S', 'serial' => '22012', 'capacity' => 0.6],
            ['name' => 'MITSUBISHI #2', 'type' => 'S16R-PTA-S', 'serial' => '21957', 'capacity' => 0.6],
        ],
        'PLTD-EREKE' => [
            ['name' => 'DAIHATSU #3', 'type' => '6 DS 26', 'serial' => '626666', 'capacity' => 0.5],
            ['name' => 'DAIHATSU #5', 'type' => '6 PSHTc-26Dm', 'serial' => '6365152', 'capacity' => 0.6],
            ['name' => 'DAIHATSU #6', 'type' => '6 PSHTc-26Dm', 'serial' => '6265153', 'capacity' => 0.6],
            ['name' => 'DAIHATSU #8', 'type' => '6 PSHTc-26Dm', 'serial' => '6265170', 'capacity' => 0.6],
            ['name' => 'CUMMINS #1', 'type' => 'KTA-50-G8', 'serial' => '25422314', 'capacity' => 0.8],
            ['name' => 'CUMMINS #2', 'type' => 'QSK23 - G3', 'serial' => '85002809', 'capacity' => 0.8],
            ['name' => 'CUMMINS #3', 'type' => 'KTA 50 G8', 'serial' => '25426235', 'capacity' => 0.8],
        ],
        'PLTM-MIKUASI' => [
            ['name' => 'ZHEJIANG JINLUN #1', 'type' => 'HLA 696-WJ-50', 'serial' => '118', 'capacity' => 0.4],
        ],
        'PLTM-SABILAMBO' => [
            ['name' => 'ZHEJIANG JINLUN #1', 'type' => 'HLA575C-WJ.60', 'serial' => '117/09152', 'capacity' => 0.4],
            ['name' => 'ZHEJIANG JINLUN #2', 'type' => 'HLA575C-WJ.60', 'serial' => '116/09153', 'capacity' => 0.4],
        ],
        'PLTU-MORAMO' => [
            ['name' => 'MORAMO #1', 'type' => '-', 'serial' => '-', 'capacity' => 1.7],
            ['name' => 'MORAMO #2', 'type' => '-', 'serial' => '-', 'capacity' => 1.7],
        ],
        'PLTMG-KENDARI' => [
            ['name' => 'WARTSILA #1', 'type' => 'W 20 V34 D', 'serial' => 'PAAE312473', 'capacity' => null],
            ['name' => 'WARTSILA #2', 'type' => 'W 20 V34 D', 'serial' => 'PAAE312474', 'capacity' => null],
            ['name' => 'WARTSILA #3', 'type' => 'W 20 V34 D', 'serial' => 'PAAE312489', 'capacity' => null],
            ['name' => 'WARTSILA #4', 'type' => 'W 20 V34 D', 'serial' => 'PAAE312490', 'capacity' => null],
            ['name' => 'WARTSILA #5', 'type' => 'W 20 V34 D', 'serial' => 'PAAE312491', 'capacity' => null],
            ['name' => 'WARTSILA #6', 'type' => 'W 20 V34 D', 'serial' => 'PAAE312492', 'capacity' => null],
        ],
        'PLTU-BARUTA' => [
            ['name' => 'BARUTA #1', 'type' => '-', 'serial' => '-', 'capacity' => null],
            ['name' => 'BARUTA #2', 'type' => '-', 'serial' => '-', 'capacity' => null],
        ],
        'PLTM-RONGI' => [
            ['name' => 'ZHEJIANG JINLUN #1', 'type' => 'HLA548-WJ-55', 'serial' => '9011', 'capacity' => null],
            ['name' => 'ZHEJIANG JINLUN #2', 'type' => 'HLA548-WJ-55', 'serial' => '9012', 'capacity' => null],
        ],
        'PLTMG-BAUBAU' => [
            ['name' => 'WARTSILA #1', 'type' => 'W 20 V34 DF', 'serial' => 'PAAE324051', 'capacity' => null],
            ['name' => 'WARTSILA #2', 'type' => 'W 20 V34 DF', 'serial' => 'PAAE324052', 'capacity' => null],
            ['name' => 'WARTSILA #3', 'type' => 'W 20 V34 DF', 'serial' => 'PAAE324053', 'capacity' => null],
            ['name' => 'WARTSILA #4', 'type' => 'W 20 V34 DF', 'serial' => 'PAAE324054', 'capacity' => null],
        ],
        'PLTG-KOLAKA' => [
            ['name' => 'PLTG Kolaka #01', 'type' => 'W 20 V34 DF', 'serial' => 'PAAE324054', 'capacity' => null],
        ],
    ];

    public function run(): void
    {
        $unitIds = Unit::query()->pluck('id', 'code');

        foreach (self::MACHINES as $unitCode => $machines) {
            $unitId = $unitIds[$unitCode] ?? null;

            if ($unitId === null) {
                continue;
            }

            foreach ($machines as $machine) {
                Machine::query()->updateOrCreate(
                    ['unit_id' => $unitId, 'name' => $machine['name']],
                    [
                        'type' => $machine['type'],
                        'serial_number' => $machine['serial'],
                        'capacity_kw' => $machine['capacity'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
