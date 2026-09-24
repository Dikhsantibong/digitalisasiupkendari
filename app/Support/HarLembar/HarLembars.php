<?php

namespace App\Support\HarLembar;

/**
 * Registry lembar matriks Modul HAR, urut menu.
 */
final class HarLembars
{
    /** @var list<class-string<HarLembar>> */
    public const ALL = [
        InventarisasiToolsLembar::class,
        PemeriksaanBlackstartLembar::class,
        PatrolCheckPemeliharaanLembar::class,
    ];

    /**
     * @return list<HarLembar>
     */
    public static function all(): array
    {
        return array_map(fn (string $class): HarLembar => new $class, self::ALL);
    }

    public static function find(string $key): ?HarLembar
    {
        foreach (self::all() as $lembar) {
            if ($lembar->key() === $key) {
                return $lembar;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function keysFor(string $menu): array
    {
        return array_values(array_map(
            fn (HarLembar $lembar): string => $lembar->key(),
            array_filter(self::all(), fn (HarLembar $lembar): bool => $lembar->menu() === $menu),
        ));
    }
}
