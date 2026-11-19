<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Short login e-mails of the application's accounts (user, 2026-11-19): the
 * jabatan and the unit are abbreviated, the domain stays —
 * `koordinator-logistik.pltd-poasia-cont@upkendari.co.id` becomes
 * `koord.log.poasiacont@upkendari.co.id`, `project-leader.pltd-baubau@…`
 * becomes `pl.baubau@…`, `operator1.pltd-kolaka@…` becomes `op1.kolaka@…`.
 *
 * Units: the code without the PLTD prefix and without hyphens (PLTD-POASIA-CONT
 * → poasiacont); other types keep their prefix (PLTG-KOLAKA → pltgkolaka) so
 * every unit stays unique. A UL (Admin UL, Manager UL) drops "ul" and "pltd"
 * the same way (UL PLTD Poasia → poasia).
 *
 * {@see self::short()} is idempotent and leaves any other address (a personal
 * e-mail, the kantor induk accounts) unchanged, so it also maps a legacy
 * address typed at login to the account's current one.
 */
class AccountEmail
{
    public const DOMAIN = 'upkendari.co.id';

    /** Legacy jabatan prefix => short prefix. */
    private const PREFIXES = [
        'tl-operasi' => 'tl.ops',
        'tl-har' => 'tl.har',
        'tl-k3' => 'tl.k3',
        'tl-ophar' => 'tl.ophar',
        'project-leader' => 'pl',
        'site-leader' => 'sl',
        'unit' => 'unit',
        'koordinator-operasi' => 'koord.ops',
        'koord-operasi' => 'koord.ops',
        'koordinator-har' => 'koord.har',
        'koord-har' => 'koord.har',
        'koordinator-k3' => 'koord.k3',
        'koord-k3' => 'koord.k3',
        'koordinator-logistik' => 'koord.log',
        'koord-logistik' => 'koord.log',
        'koordinator-pdm' => 'koord.pdm',
        'koord-pdm' => 'koord.pdm',
        'office-operasi' => 'ofc.ops',
        'office-har' => 'ofc.har',
        'office-k3' => 'ofc.k3',
        'office-logistik' => 'ofc.log',
        'office-pdm' => 'ofc.pdm',
        'pic-pdm' => 'pic.pdm',
        'staf-operasi' => 'staf.ops',
        'staf-har' => 'staf.har',
        'staf-k3' => 'staf.k3',
    ];

    /** Legacy prefix of a UL account => short prefix. */
    private const UL_PREFIXES = ['admin' => 'admin', 'manager' => 'mgr'];

    /**
     * The short form of an account e-mail (unchanged when it is not one of
     * the application's `{jabatan}.{unit}@upkendari.co.id` addresses).
     *
     * @param  bool  $ophar  the account is a TL Ophar (its legacy address may still say tl-operasi)
     */
    public static function short(string $email, bool $ophar = false): string
    {
        $email = Str::lower(trim($email));
        if (! str_ends_with($email, '@'.self::DOMAIN)) {
            return $email;
        }

        $local = Str::beforeLast($email, '@');
        if (! str_contains($local, '.')) {
            return $email; // kantor induk accounts (manager-up, tl-operasi-up, …)
        }

        $prefix = Str::before($local, '.');
        $rest = Str::after($local, '.');

        if (isset(self::UL_PREFIXES[$prefix])) {
            return self::UL_PREFIXES[$prefix].'.'.self::ulKey($rest).'@'.self::DOMAIN;
        }

        if ($ophar && $prefix === 'tl-operasi') {
            $prefix = 'tl-ophar';
        }

        $short = match (true) {
            isset(self::PREFIXES[$prefix]) => self::PREFIXES[$prefix],
            (bool) preg_match('/^operator(\d+)$/', $prefix, $m) => 'op'.$m[1],
            default => $prefix,
        };

        return $short.'.'.self::unitKey($rest).'@'.self::DOMAIN;
    }

    /**
     * An account e-mail of a unit: `{jabatan prefix}.{unit}` (legacy prefixes
     * like "koordinator-logistik" are shortened too).
     */
    public static function forUnit(string $prefix, string $unitCode): string
    {
        return self::short($prefix.'.'.(Str::slug($unitCode) ?: 'unit').'@'.self::DOMAIN);
    }

    /**
     * The short key of a unit code or slug: PLTD-POASIA-CONT → poasiacont,
     * PLTG-KOLAKA → pltgkolaka. Anything after a dot (an already short
     * address) is left as it is.
     */
    public static function unitKey(string $unit): string
    {
        if (str_contains($unit, '.')) {
            return $unit;
        }

        $slug = Str::slug($unit);
        $slug = (string) preg_replace('/^pltd-/', '', $slug);

        return str_replace('-', '', $slug) ?: 'unit';
    }

    /**
     * The short key of a UL: "ul-pltd-poasia" / "UL PLTD Poasia" / "ul-poasia" → poasia.
     */
    public static function ulKey(string $ul): string
    {
        $slug = Str::slug($ul);
        $slug = (string) preg_replace('/^ul-/', '', $slug);
        $slug = (string) preg_replace('/^pltd-/', '', $slug);

        return str_replace('-', '', $slug) ?: 'ul';
    }
}
