<?php

namespace Database\Seeders;

use App\Models\ShiftPattern;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The 8-day rotation per regu, seeded for every unit. Regu A is the base
 * pattern seen in the Excel (OFF,OFF,S,S,P,P,M,M); B and C are phase-shifted by
 * a third of the cycle so the three regus cover Pagi/Sore/Malam each day. These
 * only feed the optional "Generate pola" button — cells stay hand-editable.
 * Idempotent (keyed by unit + regu).
 */
class ShiftPatternSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var list<string> */
    private const BASE = ['OFF', 'OFF', 'S', 'S', 'P', 'P', 'M', 'M'];

    /** @var array<string, int> regu => phase offset within the cycle */
    private const PHASES = ['A' => 0, 'B' => 3, 'C' => 6];

    public function run(): void
    {
        foreach (Unit::query()->get(['id']) as $unit) {
            foreach (self::PHASES as $regu => $phase) {
                $sequence = $this->rotate(self::BASE, $phase);

                ShiftPattern::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'regu' => $regu],
                    [
                        'sequence' => implode(',', $sequence),
                        'cycle_days' => count(self::BASE),
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function rotate(array $codes, int $by): array
    {
        $by %= count($codes);

        return array_merge(array_slice($codes, $by), array_slice($codes, 0, $by));
    }
}
