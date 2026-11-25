<?php

namespace App\Services\Operator;

use App\Models\OperasiPatrolCheckEntry;
use App\Models\OperatorMutasi;
use Illuminate\Support\Carbon;

/**
 * The abnormal Patrol Check Mesin findings of a Lembar Mutasi shift (user,
 * 2026-11-24): every item marked TN (tidak normal) in a patrol check whose
 * check time falls in the shift — pagi 08–16, sore 16–22, malam 22–08 the
 * next day (WITA) — so the regu hands the abnormality over (serah terima).
 */
class MutasiPatrolFindings
{
    /** Shift windows as [start hour, end hour] on the sheet's date (end > 24 = next day). */
    private const WINDOWS = ['pagi' => [8, 16], 'sore' => [16, 22], 'malam' => [22, 32]];

    /**
     * @return list<array{jam: string, mesin: string, peralatan: string, system: string, catatan: string, operator: string}>
     */
    public function findings(int $unitId, string $tanggal, string $shift): array
    {
        if (! array_key_exists($shift, OperatorMutasi::SHIFTS)) {
            return [];
        }

        [$fromHour, $toHour] = self::WINDOWS[$shift];
        $day = Carbon::parse($tanggal)->startOfDay();
        $from = $day->copy()->addHours($fromHour);
        $to = $day->copy()->addHours($toHour);

        $entries = OperasiPatrolCheckEntry::query()
            ->with('patrolCheckMesin:id,unit_id,nama_mesin')
            ->whereHas('patrolCheckMesin', fn ($q) => $q->where('unit_id', $unitId))
            ->whereDate('tanggal', '>=', $day->toDateString())
            ->whereDate('tanggal', '<=', $to->toDateString())
            ->get();

        $findings = [];
        foreach ($entries as $entry) {
            [$hour, $minute] = array_map('intval', array_pad(explode(':', str_replace('.', ':', (string) $entry->jam)), 2, 0));
            $at = Carbon::parse($entry->tanggal->toDateString())->setTime($hour, $minute);
            if ($at->lessThan($from) || $at->greaterThanOrEqualTo($to)) {
                continue;
            }

            foreach ((array) $entry->items as $item) {
                if (($item['status'] ?? '') !== 'TN') {
                    continue;
                }
                $findings[] = [
                    'at' => $at,
                    'jam' => $at->format('H:i'),
                    'mesin' => (string) ($entry->patrolCheckMesin?->nama_mesin ?? ''),
                    'peralatan' => (string) ($item['peralatan'] ?? ''),
                    'system' => (string) ($item['system'] ?? ''),
                    'catatan' => (string) ($item['catatan'] ?? ''),
                    'operator' => (string) $entry->operator_name,
                ];
            }
        }

        usort($findings, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return array_map(function (array $f): array {
            unset($f['at']);

            return $f;
        }, $findings);
    }
}
