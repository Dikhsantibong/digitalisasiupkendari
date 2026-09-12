<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * A small roster of employees per unit so the Absensi schedule grid has rows to
 * work with: six shift operators (regu A/B/C, two each) and two non-shift staff.
 * Idempotent (keyed by NIP). Real rosters are maintained through the Pegawai
 * master; this only bootstraps a usable demo.
 */
class EmployeeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{suffix: int, position: string, regu: ?string}>
     */
    private const ROSTER = [
        ['suffix' => 1, 'position' => 'Operator', 'regu' => 'A'],
        ['suffix' => 2, 'position' => 'Operator', 'regu' => 'A'],
        ['suffix' => 3, 'position' => 'Operator', 'regu' => 'B'],
        ['suffix' => 4, 'position' => 'Operator', 'regu' => 'B'],
        ['suffix' => 5, 'position' => 'Operator', 'regu' => 'C'],
        ['suffix' => 6, 'position' => 'Operator', 'regu' => 'C'],
        ['suffix' => 7, 'position' => 'Koordinator', 'regu' => null],
        ['suffix' => 8, 'position' => 'Teknisi Pemeliharaan', 'regu' => null],
    ];

    public function run(): void
    {
        foreach (Unit::query()->orderBy('id')->get(['id', 'code', 'name']) as $unit) {
            $prefix = strtoupper(substr((string) ($unit->code ?? 'U'.$unit->id), 0, 4));

            foreach (self::ROSTER as $person) {
                $nip = sprintf('%s-PEG-%03d', $prefix, $person['suffix']);
                $label = $person['regu'] !== null
                    ? "{$person['position']} Regu {$person['regu']}"
                    : $person['position'];

                Employee::query()->updateOrCreate(
                    ['nip' => $nip],
                    [
                        'unit_id' => $unit->id,
                        'name' => "{$label} {$person['suffix']} — {$unit->name}",
                        'position' => $person['position'],
                        'regu' => $person['regu'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
