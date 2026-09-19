<?php

namespace Database\Seeders;

use App\Enums\EmployeePosition;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Standard roster per generating unit:
 * - 6 Shift Operators (Regu A, B, C, 2 each)
 * - 1 Team Leader Pemeliharaan, Operasi, K3 & Keamanan
 * - 1 Staf Pemeliharaan, Operasi, K3
 * - 1 Koordinator Pemeliharaan, Operasi, K3, PDM, Logistik
 * - 1 Project Leader
 * - 1 Office Pemeliharaan, Operasi, K3, PDM, Logistik (1 per unit + divisi)
 * - 1 PIC PDM
 * And 1 Manager UL for each Service Unit.
 *
 * Idempotent (keyed by NIP). The report-signer jabatan
 * ({@see EmployeePosition}) get their divisi and one-per-unit key here too —
 * the seeder runs without model events — and a jabatan already held by
 * another active employee of the unit is left to that employee.
 */
class EmployeeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{suffix: int, position: string, regu: ?string, label: string, division: ?string}>
     */
    public const UNIT_ROSTER = [
        ['suffix' => 1, 'position' => 'Operator', 'regu' => 'A', 'label' => 'Operator 1 (Regu A)', 'division' => 'operator'],
        ['suffix' => 2, 'position' => 'Operator', 'regu' => 'A', 'label' => 'Operator 2 (Regu A)', 'division' => 'operator'],
        ['suffix' => 3, 'position' => 'Operator', 'regu' => 'B', 'label' => 'Operator 3 (Regu B)', 'division' => 'operator'],
        ['suffix' => 4, 'position' => 'Operator', 'regu' => 'B', 'label' => 'Operator 4 (Regu B)', 'division' => 'operator'],
        ['suffix' => 5, 'position' => 'Operator', 'regu' => 'C', 'label' => 'Operator 5 (Regu C)', 'division' => 'operator'],
        ['suffix' => 6, 'position' => 'Operator', 'regu' => 'C', 'label' => 'Operator 6 (Regu C)', 'division' => 'operator'],
        ['suffix' => 7, 'position' => 'Team Leader Pemeliharaan', 'regu' => null, 'label' => 'TL Pemeliharaan', 'division' => 'pemeliharaan'],
        ['suffix' => 8, 'position' => 'Team Leader Operasi', 'regu' => null, 'label' => 'TL Operasi', 'division' => 'operasi'],
        ['suffix' => 9, 'position' => 'Team Leader K3 & Keamanan', 'regu' => null, 'label' => 'TL K3 & Keamanan', 'division' => 'k3'],
        ['suffix' => 10, 'position' => 'Staf Pemeliharaan', 'regu' => null, 'label' => 'Staf Pemeliharaan', 'division' => 'pemeliharaan'],
        ['suffix' => 11, 'position' => 'Staf Operasi', 'regu' => null, 'label' => 'Staf Operasi', 'division' => 'operasi'],
        ['suffix' => 12, 'position' => 'Staf K3', 'regu' => null, 'label' => 'Staf K3', 'division' => 'k3'],
        ['suffix' => 13, 'position' => 'Koordinator Pemeliharaan', 'regu' => null, 'label' => 'Koordinator Pemeliharaan', 'division' => 'pemeliharaan'],
        ['suffix' => 14, 'position' => 'Koordinator Operasi', 'regu' => null, 'label' => 'Koordinator Operasi', 'division' => 'operasi'],
        ['suffix' => 15, 'position' => 'Koordinator K3', 'regu' => null, 'label' => 'Koordinator K3', 'division' => 'k3'],
        ['suffix' => 16, 'position' => 'Koordinator PDM', 'regu' => null, 'label' => 'Koordinator PDM', 'division' => 'pdm'],
        ['suffix' => 17, 'position' => 'Koordinator Logistik', 'regu' => null, 'label' => 'Koordinator Logistik', 'division' => 'logistik'],
        ['suffix' => 18, 'position' => 'Project Leader', 'regu' => null, 'label' => 'Project Leader', 'division' => null],
        ['suffix' => 19, 'position' => 'Office Pemeliharaan', 'regu' => null, 'label' => 'Office Pemeliharaan', 'division' => 'pemeliharaan'],
        ['suffix' => 20, 'position' => 'Office Operasi', 'regu' => null, 'label' => 'Office Operasi', 'division' => 'operasi'],
        ['suffix' => 21, 'position' => 'Office K3', 'regu' => null, 'label' => 'Office K3', 'division' => 'k3'],
        ['suffix' => 22, 'position' => 'Office PDM', 'regu' => null, 'label' => 'Office PDM', 'division' => 'pdm'],
        ['suffix' => 23, 'position' => 'Office Logistik', 'regu' => null, 'label' => 'Office Logistik', 'division' => 'logistik'],
        ['suffix' => 24, 'position' => 'PIC PDM', 'regu' => null, 'label' => 'PIC PDM', 'division' => 'pdm'],
    ];

    public function run(): void
    {
        // Remove legacy demo rows seeded with obsolete 4-char prefix (e.g. PLTD-PEG-001)
        Employee::query()->where('nip', 'like', '____-PEG-%')->delete();

        // 1. Seed the unit-level roster
        foreach (Unit::query()->orderBy('id')->get() as $unit) {
            foreach (self::UNIT_ROSTER as $person) {
                $this->seed(sprintf('%s-PEG-%03d', $unit->code, $person['suffix']), [
                    'unit_id' => $unit->id,
                    'service_unit_id' => null,
                    'name' => "{$person['label']} — {$unit->name}",
                    'position' => $person['position'],
                    'regu' => $person['regu'],
                    'division' => $person['division'],
                ]);
            }
        }

        // 2. Seed 1 Manager UL for each Service Unit
        foreach (ServiceUnit::query()->orderBy('id')->get() as $serviceUnit) {
            $this->seed("{$serviceUnit->code}-MGR", [
                'unit_id' => null,
                'service_unit_id' => $serviceUnit->id,
                'name' => "Manager {$serviceUnit->name}",
                'position' => EmployeePosition::ManagerUl->value,
                'regu' => null,
                'division' => null,
            ]);
        }
    }

    /**
     * @param  array{unit_id: int|null, service_unit_id: int|null, name: string, position: string, regu: string|null, division: string|null}  $attributes
     */
    private function seed(string $nip, array $attributes): void
    {
        $signer = Employee::signerAttributes($attributes['position'], $attributes['unit_id'], $attributes['service_unit_id'], true, $attributes['division']);

        // The jabatan already has an active holder in this unit (e.g. a real
        // employee entered through Master Pegawai): keep that one.
        if ($signer['singleton_key'] !== null && Employee::query()
            ->where('singleton_key', $signer['singleton_key'])
            ->where(fn ($query) => $query->whereNull('nip')->orWhere('nip', '!=', $nip))
            ->exists()) {
            return;
        }

        $employee = Employee::query()->firstOrNew(['nip' => $nip]);
        $employee->fill([...$attributes, 'is_active' => true, 'division' => $signer['division']]);
        $employee->singleton_key = $signer['singleton_key'];
        $employee->save();
    }
}
