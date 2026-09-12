<?php

namespace App\Enums;

/**
 * The plant type a logsheet parameter set applies to. `All` is the shared set
 * used now; per-type sets (PLTM/PLTG) can be seeded later without a schema
 * change once the machine/unit masters carry a plant-type marker.
 */
enum PlantType: string
{
    case All = 'all';
    case Pltd = 'pltd';
    case Containerized = 'containerized';
    case Pltm = 'pltm';
    case Pltg = 'pltg';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Semua',
            self::Pltd => 'PLTD',
            self::Containerized => 'Containerized',
            self::Pltm => 'PLTM',
            self::Pltg => 'PLTG',
        };
    }
}
