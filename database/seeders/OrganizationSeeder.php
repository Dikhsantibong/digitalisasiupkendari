<?php

namespace Database\Seeders;

use App\Enums\UnitType;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the UP Kendari organisation: its service units (UL) and the generating
 * units beneath them.
 *
 * Units whose parent UL has not been confirmed are seeded without one; see the
 * open questions in concept.md.
 */
class OrganizationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<string, array{name: string, description: string}>
     */
    private const SERVICE_UNITS = [
        'UL-POASIA' => [
            'name' => 'UL PLTD Poasia',
            'description' => 'Unit Layanan PLTD Poasia',
        ],
        'UL-BAUBAU' => [
            'name' => 'UL PLTD Bau-Bau',
            'description' => 'Unit Layanan PLTD Bau-Bau',
        ],
        'UL-KOLAKA' => [
            'name' => 'UL PLTD Kolaka',
            'description' => 'Unit Layanan PLTD Kolaka',
        ],
    ];

    /**
     * @var list<array{code: string, name: string, type: UnitType, service_unit: string|null}>
     */
    private const UNITS = [
        ['code' => 'PLTD-POASIA', 'name' => 'PLTD Poasia', 'type' => UnitType::Pltd, 'service_unit' => 'UL-POASIA'],
        ['code' => 'PLTD-POASIA-CONT', 'name' => 'PLTD Poasia Containerized', 'type' => UnitType::Pltd, 'service_unit' => 'UL-POASIA'],

        ['code' => 'PLTD-BAUBAU', 'name' => 'PLTD Bau-Bau', 'type' => UnitType::Pltd, 'service_unit' => 'UL-BAUBAU'],
        ['code' => 'PLTD-WANGIWANGI', 'name' => 'PLTD Wangi-Wangi', 'type' => UnitType::Pltd, 'service_unit' => 'UL-BAUBAU'],
        ['code' => 'PLTD-RAHA', 'name' => 'PLTD Raha', 'type' => UnitType::Pltd, 'service_unit' => 'UL-BAUBAU'],
        ['code' => 'PLTD-EREKE', 'name' => 'PLTD Ereke', 'type' => UnitType::Pltd, 'service_unit' => 'UL-BAUBAU'],
        ['code' => 'PLTM-RONGI', 'name' => 'PLTM Rongi', 'type' => UnitType::Pltm, 'service_unit' => 'UL-BAUBAU'],
        ['code' => 'PLTM-WINNING', 'name' => 'PLTM Winning', 'type' => UnitType::Pltm, 'service_unit' => 'UL-BAUBAU'],
        ['code' => 'PLTU-BARUTA', 'name' => 'PLTU Baruta', 'type' => UnitType::Pltu, 'service_unit' => 'UL-BAUBAU'],
        ['code' => 'PLTMG-BAUBAU', 'name' => 'PLTMG Bau-Bau', 'type' => UnitType::Pltmg, 'service_unit' => 'UL-BAUBAU'],

        ['code' => 'PLTD-KOLAKA', 'name' => 'PLTD Kolaka', 'type' => UnitType::Pltd, 'service_unit' => 'UL-KOLAKA'],
        ['code' => 'PLTG-KOLAKA', 'name' => 'PLTG Kolaka', 'type' => UnitType::Pltg, 'service_unit' => 'UL-KOLAKA'],
        ['code' => 'PLTM-SABILAMBO', 'name' => 'PLTM Sabilambo', 'type' => UnitType::Pltm, 'service_unit' => 'UL-KOLAKA'],
        ['code' => 'PLTM-MIKUASI', 'name' => 'PLTM Mikuasi', 'type' => UnitType::Pltm, 'service_unit' => 'UL-KOLAKA'],
        ['code' => 'PLTD-LANIPANIPA', 'name' => 'PLTD Lanipa-Nipa', 'type' => UnitType::Pltd, 'service_unit' => 'UL-KOLAKA'],

        ['code' => 'PLTU-MORAMO', 'name' => 'PLTU Moramo', 'type' => UnitType::Pltu, 'service_unit' => null],
        ['code' => 'PLTMG-KENDARI', 'name' => 'PLTMG Kendari', 'type' => UnitType::Pltmg, 'service_unit' => null],
        ['code' => 'PLTD-WUAWUA', 'name' => 'PLTD Wua-Wua', 'type' => UnitType::Pltd, 'service_unit' => null],
        ['code' => 'PLTD-LANGARA', 'name' => 'PLTD Langara', 'type' => UnitType::Pltd, 'service_unit' => null],
        ['code' => 'PLTM-LANGARA', 'name' => 'PLTM Langara', 'type' => UnitType::Pltm, 'service_unit' => null],
        ['code' => 'PLTD-PASARWAJO', 'name' => 'PLTD Pasarwajo', 'type' => UnitType::Pltd, 'service_unit' => null],
        ['code' => 'PLTD-LADUMPI', 'name' => 'PLTD Ladumpi', 'type' => UnitType::Pltd, 'service_unit' => null],
    ];

    public function run(): void
    {
        $serviceUnits = [];

        foreach (self::SERVICE_UNITS as $code => $attributes) {
            $serviceUnits[$code] = ServiceUnit::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $attributes['name'],
                    'slug' => Str::slug($attributes['name']),
                    'description' => $attributes['description'],
                    'is_active' => true,
                ],
            )->getKey();
        }

        foreach (self::UNITS as $unit) {
            Unit::query()->updateOrCreate(
                ['code' => $unit['code']],
                [
                    'service_unit_id' => $unit['service_unit'] === null
                        ? null
                        : $serviceUnits[$unit['service_unit']],
                    'name' => $unit['name'],
                    'slug' => Str::slug($unit['name']),
                    'type' => $unit['type'],
                    'is_active' => true,
                ],
            );
        }
    }
}
