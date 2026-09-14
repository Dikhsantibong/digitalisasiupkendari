<?php

namespace Database\Seeders;

use App\Enums\UnitType;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the UP Kendari organisation: its service units (UL), the generating
 * units beneath them, and master employees (pegawai) for each unit & service unit.
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

    /**
     * Standard roster per generating unit:
     * - 6 Shift Operators (Regu A, B, C, 2 each)
     * - 1 Team Leader Pemeliharaan
     * - 1 Team Leader Operasi
     * - 1 Team Leader K3 & Keamanan
     *
     * @var list<array{suffix: int, position: string, regu: ?string, label: string}>
     */
    private const UNIT_ROSTER = [
        ['suffix' => 1, 'position' => 'Operator', 'regu' => 'A', 'label' => 'Operator 1 (Regu A)'],
        ['suffix' => 2, 'position' => 'Operator', 'regu' => 'A', 'label' => 'Operator 2 (Regu A)'],
        ['suffix' => 3, 'position' => 'Operator', 'regu' => 'B', 'label' => 'Operator 3 (Regu B)'],
        ['suffix' => 4, 'position' => 'Operator', 'regu' => 'B', 'label' => 'Operator 4 (Regu B)'],
        ['suffix' => 5, 'position' => 'Operator', 'regu' => 'C', 'label' => 'Operator 5 (Regu C)'],
        ['suffix' => 6, 'position' => 'Operator', 'regu' => 'C', 'label' => 'Operator 6 (Regu C)'],
        ['suffix' => 7, 'position' => 'Team Leader Pemeliharaan', 'regu' => null, 'label' => 'TL Pemeliharaan'],
        ['suffix' => 8, 'position' => 'Team Leader Operasi', 'regu' => null, 'label' => 'TL Operasi'],
        ['suffix' => 9, 'position' => 'Team Leader K3 & Keamanan', 'regu' => null, 'label' => 'TL K3 & Keamanan'],
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

        $this->seedEmployees();
    }

    /**
     * Seeds master employees for each unit:
     * - 6 operators (Regu A/B/C)
     * - 1 TL Pemeliharaan
     * - 1 TL Operasi
     * - 1 TL K3 & Keamanan
     * And 1 Manager UL for each Service Unit overseeing multiple units (e.g. UL PLTD Poasia overseeing PLTD Poasia & Containerized).
     *
     * Idempotent: skips if the employee data already exists.
     */
    public function seedEmployees(): void
    {
        $units = Unit::query()->orderBy('id')->get();

        // 1. Seed unit-level roster (6 Operators, 1 TL Har, 1 TL Operasi, 1 TL K3)
        foreach ($units as $unit) {
            foreach (self::UNIT_ROSTER as $person) {
                $nip = sprintf('%s-PEG-%03d', $unit->code, $person['suffix']);
                $name = "{$person['label']} — {$unit->name}";

                if ($this->employeeExists($nip, $unit->id, $name)) {
                    continue;
                }

                Employee::query()->create([
                    'unit_id' => $unit->id,
                    'name' => $name,
                    'nip' => $nip,
                    'position' => $person['position'],
                    'regu' => $person['regu'],
                    'is_active' => true,
                ]);
            }
        }

        // 2. Seed 1 Manager UL for each Service Unit (e.g. UL PLTD Poasia covers PLTD Poasia & PLTD Poasia Containerized)
        foreach (self::SERVICE_UNITS as $suCode => $suAttributes) {
            $serviceUnit = ServiceUnit::query()->where('code', $suCode)->first();
            if (! $serviceUnit) {
                continue;
            }

            $mgrNip = "{$suCode}-MGR";
            $mgrName = "Manager {$serviceUnit->name}";

            $existing = Employee::query()
                ->where('nip', $mgrNip)
                ->orWhere(function ($query) use ($serviceUnit, $mgrName): void {
                    $query->where('service_unit_id', $serviceUnit->id)->where('name', $mgrName);
                })
                ->first();

            if ($existing) {
                $existing->update([
                    'service_unit_id' => $serviceUnit->id,
                    'unit_id' => null,
                ]);
                continue;
            }

            Employee::query()->create([
                'unit_id' => null,
                'service_unit_id' => $serviceUnit->id,
                'name' => $mgrName,
                'nip' => $mgrNip,
                'position' => 'Manager UL',
                'regu' => null,
                'is_active' => true,
            ]);
        }
    }

    private function employeeExists(string $nip, ?int $unitId, string $name, ?int $serviceUnitId = null): bool
    {
        return Employee::query()
            ->where('nip', $nip)
            ->orWhere(function ($query) use ($unitId, $serviceUnitId, $name): void {
                if ($unitId !== null) {
                    $query->where('unit_id', $unitId)->where('name', $name);
                }
                if ($serviceUnitId !== null) {
                    $query->where('service_unit_id', $serviceUnitId)->where('name', $name);
                }
            })
            ->exists();
    }
}
