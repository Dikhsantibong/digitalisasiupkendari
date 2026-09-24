<?php

namespace App\Support\HarTabel;

/**
 * Registry tabel input bebas Modul HAR.
 */
final class HarTabels
{
    /** @var list<class-string<HarTabel>> */
    public const ALL = [
        RekapGangguanTabel::class,
        AbnormalGangguanTabel::class,
    ];

    /**
     * @return list<HarTabel>
     */
    public static function all(): array
    {
        return array_map(fn (string $class): HarTabel => new $class, self::ALL);
    }

    public static function find(string $key): ?HarTabel
    {
        foreach (self::all() as $tabel) {
            if ($tabel->key() === $key) {
                return $tabel;
            }
        }

        return null;
    }
}
