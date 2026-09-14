<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Standard roster per generating unit:
 * - 6 Shift Operators (Regu A, B, C, 2 each)
 * - 1 Team Leader Pemeliharaan
 * - 1 Team Leader Operasi
 * - 1 Team Leader K3 & Keamanan
 * - 1 Staf Pemeliharaan
 * - 1 Staf Operasi
 * - 1 Staf K3
 * And 1 Manager UL for each Service Unit.
 *
 * Idempotent (keyed by NIP).
 */
class EmployeeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Standard roster per generating unit:
     * - 6 Shift Operators (Regu A, B, C, 2 each)
     * - 1 Team Leader Pemeliharaan
     * - 1 Team Leader Operasi
     * - 1 Team Leader K3 & Keamanan
     * - 1 Staf Pemeliharaan
     * - 1 Staf Operasi
     * - 1 Staf K3
     *
     * @var list<array{suffix: int, position: string, regu: ?string, label: string}>
     */
    public const UNIT_ROSTER = [
        ['suffix' => 1, 'position' => 'Operator', 'regu' => 'A', 'label' => 'Operator 1 (Regu A)'],
        ['suffix' => 2, 'position' => 'Operator', 'regu' => 'A', 'label' => 'Operator 2 (Regu A)'],
        ['suffix' => 3, 'position' => 'Operator', 'regu' => 'B', 'label' => 'Operator 3 (Regu B)'],
        ['suffix' => 4, 'position' => 'Operator', 'regu' => 'B', 'label' => 'Operator 4 (Regu B)'],
        ['suffix' => 5, 'position' => 'Operator', 'regu' => 'C', 'label' => 'Operator 5 (Regu C)'],
        ['suffix' => 6, 'position' => 'Operator', 'regu' => 'C', 'label' => 'Operator 6 (Regu C)'],
        ['suffix' => 7, 'position' => 'Team Leader Pemeliharaan', 'regu' => null, 'label' => 'TL Pemeliharaan'],
        ['suffix' => 8, 'position' => 'Team Leader Operasi', 'regu' => null, 'label' => 'TL Operasi'],
        ['suffix' => 9, 'position' => 'Team Leader K3 & Keamanan', 'regu' => null, 'label' => 'TL K3 & Keamanan'],
        ['suffix' => 10, 'position' => 'Staf Pemeliharaan', 'regu' => null, 'label' => 'Staf Pemeliharaan'],
        ['suffix' => 11, 'position' => 'Staf Operasi', 'regu' => null, 'label' => 'Staf Operasi'],
        ['suffix' => 12, 'position' => 'Staf K3', 'regu' => null, 'label' => 'Staf K3'],
    ];

    public function run(): void
    {
        // Remove legacy demo rows seeded with obsolete 4-char prefix (e.g. PLTD-PEG-001)
        Employee::query()->where('nip', 'like', '____-PEG-%')->delete();

        $units = Unit::query()->orderBy('id')->get();

        // 1. Seed unit-level roster (6 Operators, 1 TL Har, 1 TL Operasi, 1 TL K3, 1 Staf Har, 1 Staf Operasi, 1 Staf K3)
        foreach ($units as $unit) {
            foreach (self::UNIT_ROSTER as $person) {
                $nip = sprintf('%s-PEG-%03d', $unit->code, $person['suffix']);
                $name = "{$person['label']} — {$unit->name}";

                Employee::query()->updateOrCreate(
                    ['nip' => $nip],
                    [
                        'unit_id' => $unit->id,
                        'service_unit_id' => null,
                        'name' => $name,
                        'position' => $person['position'],
                        'regu' => $person['regu'],
                        'is_active' => true,
                    ],
                );
            }
        }

        // 2. Seed 1 Manager UL for each Service Unit
        foreach (ServiceUnit::query()->orderBy('id')->get() as $serviceUnit) {
            $mgrNip = "{$serviceUnit->code}-MGR";
            $mgrName = "Manager {$serviceUnit->name}";

            Employee::query()->updateOrCreate(
                ['nip' => $mgrNip],
                [
                    'unit_id' => null,
                    'service_unit_id' => $serviceUnit->id,
                    'name' => $mgrName,
                    'position' => 'Manager UL',
                    'regu' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
