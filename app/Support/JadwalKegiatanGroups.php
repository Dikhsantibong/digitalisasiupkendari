<?php

namespace App\Support;

/**
 * The groups of every divisi's Jadwal Kegiatan (user, 2026-11-25): activities
 * are planned as I. Harian, II. Mingguan, III. Bulanan and IV. Non Rutin.
 * Mirrored by resources/js/lib/jadwal-kegiatan.ts.
 */
final class JadwalKegiatanGroups
{
    /** key => [roman, label] in display order. */
    public const GROUPS = [
        'harian' => ['I', 'Harian'],
        'mingguan' => ['II', 'Mingguan'],
        'bulanan' => ['III', 'Bulanan'],
        'non_rutin' => ['IV', 'Non Rutin'],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::GROUPS);
    }

    public static function label(string $grup): string
    {
        return self::GROUPS[self::normalize($grup)][1];
    }

    /** Unknown / legacy values (e.g. "non-rutin", "NON RUTIN") fall into their group, else Harian. */
    public static function normalize(string $grup): string
    {
        $key = str_replace([' ', '-'], '_', strtolower(trim($grup)));

        return array_key_exists($key, self::GROUPS) ? $key : 'harian';
    }

    /** Proposed target for a new activity: every working day, 4×, 1× a month, as needed. */
    public static function defaultTarget(string $grup, int $workingDays): int
    {
        return match (self::normalize($grup)) {
            'mingguan' => 4,
            'bulanan' => 1,
            'non_rutin' => 0,
            default => $workingDays,
        };
    }
}
