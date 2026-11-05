<?php

namespace App\Enums;

/**
 * Where a kWh meter of the Opname kWh counts: kWh produksi, kWh pemakaian
 * sendiri (PS) or the susut trafo (kWh PSST = PS + susut trafo).
 */
enum KwhMeterKategori: string
{
    case Produksi = 'produksi';
    case Ps = 'ps';
    case SusutTrafo = 'susut_trafo';

    public function label(): string
    {
        return match ($this) {
            self::Produksi => 'kWh Produksi',
            self::Ps => 'kWh PS',
            self::SusutTrafo => 'Susut Trafo',
        };
    }
}
