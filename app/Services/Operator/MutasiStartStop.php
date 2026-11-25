<?php

namespace App\Services\Operator;

use App\Models\OperatorMutasi;
use App\Models\OperatorStartStop;
use Illuminate\Support\Carbon;

/**
 * The start / stop events of a Lembar Mutasi shift, read from the Start Stop
 * Engine input (user, 2026-11-24) instead of being typed again: every start
 * and stop of the unit's machines inside the shift window — pagi 08–16, sore
 * 16–22, malam 22–08 the next day (WITA). A stop earlier than its start falls
 * on the next day.
 */
class MutasiStartStop
{
    private const TIMEZONE = 'Asia/Makassar';

    /** Shift windows as [start hour, end hour] on the sheet's date (end > 24 = next day). */
    private const WINDOWS = ['pagi' => [8, 16], 'sore' => [16, 22], 'malam' => [22, 32]];

    /**
     * @return list<array{jam: string, uraian: string, mesin: string, jenis: string}>
     */
    public function events(int $unitId, string $tanggal, string $shift): array
    {
        if (! array_key_exists($shift, OperatorMutasi::SHIFTS)) {
            return [];
        }

        [$fromHour, $toHour] = self::WINDOWS[$shift];
        $day = Carbon::parse($tanggal, self::TIMEZONE)->startOfDay();
        $from = $day->copy()->addHours($fromHour);
        $to = $day->copy()->addHours($toHour);

        $records = OperatorStartStop::query()
            ->with('engine:id,name')
            ->where('unit_id', $unitId)
            // A record of the previous day may stop inside this window.
            ->whereDate('tanggal', '>=', $day->copy()->subDay()->toDateString())
            ->whereDate('tanggal', '<=', $to->toDateString())
            ->get();

        $events = [];
        foreach ($records as $record) {
            $date = Carbon::parse($record->tanggal->toDateString(), self::TIMEZONE);
            $mesin = (string) ($record->engine?->name ?? 'Mesin');
            $start = $this->at($date, $record->start_time);
            $stop = $this->at($date, $record->stop_time);
            if ($start !== null && $stop !== null && $stop->lessThan($start)) {
                $stop = $stop->addDay();
            }

            if ($start !== null && $start->greaterThanOrEqualTo($from) && $start->lessThan($to)) {
                $events[] = [
                    'at' => $start,
                    'jam' => $start->format('H:i'),
                    'mesin' => $mesin,
                    'jenis' => 'start',
                    'uraian' => "Start {$mesin}".($record->cbg_on ? " (CBG ON {$this->hm($record->cbg_on)})" : ''),
                ];
            }
            if ($stop !== null && $stop->greaterThanOrEqualTo($from) && $stop->lessThan($to)) {
                $cause = OperatorStartStop::PENYEBAB[$record->penyebab_stop] ?? $record->penyebab_stop;
                $events[] = [
                    'at' => $stop,
                    'jam' => $stop->format('H:i'),
                    'mesin' => $mesin,
                    'jenis' => 'stop',
                    'uraian' => "Stop {$mesin} — {$cause}".($record->keterangan ? ": {$record->keterangan}" : '')
                        .($record->cbg_off ? " (CBG OFF {$this->hm($record->cbg_off)})" : ''),
                ];
            }
        }

        usort($events, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return array_map(fn (array $e): array => ['jam' => $e['jam'], 'uraian' => $e['uraian'], 'mesin' => $e['mesin'], 'jenis' => $e['jenis']], $events);
    }

    private function at(Carbon $date, ?string $time): ?Carbon
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        [$hour, $minute] = array_map('intval', array_pad(explode(':', $time), 2, 0));

        return $date->copy()->setTime($hour, $minute);
    }

    private function hm(string $time): string
    {
        return substr($time, 0, 5);
    }
}
