<?php

namespace App\Enums;

/**
 * Where a kWh meter of the Opname kWh counts: kWh produksi, kWh pemakaian
 * sendiri (PS) or the susut trafo (kWh PSST = PS + susut trafo). Netto
 * (user, 2026-11-26): a meter that reads the netto itself — recorded next to
 * the netto computed as produksi − PSST, which stays the figure used for SFC.
 */
enum KwhMeterKategori: string
{
    case Produksi = 'produksi';
    case Ps = 'ps';
    case SusutTrafo = 'susut_trafo';
    case Netto = 'netto';

    public function label(): string
    {
        return match ($this) {
            self::Produksi => 'kWh Produksi',
            self::Ps => 'kWh PS',
            self::SusutTrafo => 'Susut Trafo',
            self::Netto => 'kWh Netto',
        };
    }
}
